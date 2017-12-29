<?php

namespace CASHMusic\Admin;

use CASHMusic\Core\CASHSystem as CASHSystem;
use CASHMusic\Core\CASHRequest as CASHRequest;
use ArrayIterator;
use CASHMusic\Admin\AdminHelper;

$admin_helper = new AdminHelper($admin_request, $cash_admin);
//Asset connections?
$cash_admin->page_data['connection'] = $admin_helper->getConnectionsByScope('assets');

$user_id = $cash_admin->effective_user_id;

// Deal with download code requests
if (isset($_REQUEST['add_codes_qty']) && $request_parameters[0]) {
	if ($_REQUEST['add_codes_qty'] > 0) {
		$total_added = 0;

        $addcode_response = $admin_request->request('asset')
                                ->action('addbulklockcodes')
                                ->with([
                                    'asset_id' => $request_parameters[0],
                                    'code_count' => $_POST['add_codes_qty']
								])->get();

        if ($addcode_response['payload']) {
            $total_added = $_POST['add_codes_qty'];
        }

		$cash_admin->page_data['page_message'] = 'Added ' . $total_added . ' new download codes';
	}
}

$asset_codes = false;
if ($request_parameters[0]) {

	$getcodes_response = $admin_request->request('system')
	                        ->action('getlockcodes')
	                        ->with([
                                'scope_table_alias' => 'assets',
                                'scope_table_id' => $request_parameters[0]
							])->get();

	$asset_codes = $getcodes_response['payload'];
}
if (isset($_REQUEST['exportcodes']) && $request_parameters[0]) {
	header('Content-Disposition: attachment; filename="codes_' . $request_parameters[0] . '_export.csv"');
	if ($asset_codes) {
		echo '"code","creation date","claim date"' . "\n";
		foreach ($asset_codes as $code) {
		    echo '"' . $code->uid . '"';
			echo ',"' . date('M j, Y h:iA T',$code->creation_date) . '"';
			if ($code->claim_date) {
				echo ',"' . date('M j, Y h:iA T',$code->claim_date) . '"';
			} else {
				echo ',"not claimed"';
			}
			echo "\n";
		}
	} else {
		$cash_admin->page_data['error_message'] = "Error getting codes.";
	}
	exit;
}

// parsing posted data:
if (isset($_POST['doassetedit'])) {
	$asset_parent = false;
	$connection_id = 0;
	$asset_location = '';
	$asset_description = false;
	$metadata = false;
	if (isset($_POST['parent_id'])) $asset_parent = $_POST['parent_id'];
	if (isset($_POST['connection_id'])) $connection_id = $_POST['connection_id'];
	if (isset($_POST['asset_location'])) $asset_location = $_POST['asset_location'];
	if (isset($_POST['asset_description'])) $asset_description = $_POST['asset_description'];

	$metadata_and_tags = AdminHelper::parseMetaData($_POST);
	

	if ($_POST['asset_type'] == 'release') {
		$metadata = array(
			'artist_name' => $_POST['artist_name'],
			'release_date' => $_POST['release_date'],
			'matrix_number' => $_POST['matrix_number'],
			'label_name' => isset($_POST['label_name']) ? $_POST['label_name'] : false,
			'genre' => isset($_POST['genre']) ? $_POST['genre'] : false,
			'copyright' => isset($_POST['copyright']) ? $_POST['copyright'] : false,
			'publishing' => isset($_POST['publishing']) ? $_POST['publishing'] : false,
			'fulfillment' => json_decode($_POST['metadata_fulfillment']),
			'private' => isset($_POST['metadata_private']) ? json_decode($_POST['metadata_private']) : false,
			'cover' => isset($_POST['metadata_cover']) ? $_POST['metadata_cover'] : false,
			'publicist_name' => isset($_POST['publicist_name']) ? $_POST['publicist_name'] : false,
			'publicist_email' => isset($_POST['publicist_email']) ? $_POST['publicist_email'] : false,
			'onesheet' => isset($_POST['onesheet']) ? $_POST['onesheet'] : false
		);
	}

	$edit_response = $admin_request->request('asset')
	                        ->action('editasset')
	                        ->with([
                                'id' => $request_parameters[0],
                                'user_id' => $user_id,
                                'title' => $_POST['asset_title'],
                                'description' => $asset_description,
                                'location' => $asset_location,
                                'connection_id' => $connection_id,
                                'parent_id' => $asset_parent,
                                'type' => $_POST['asset_type'],
                                'tags' => $metadata_and_tags['tags_details'],
                                'metadata' => $metadata
							])->get();

	if (!$edit_response['payload']) {
		$cash_admin->page_data['error_message'] = "Error editing asset. Please try again";
	} else {
		$cash_admin->page_data['page_message'] = 'Success. Edited.';
	}
}

// Get the current asset details:
$asset_response = $admin_request->request('asset')
                        ->action('getasset')
                        ->with(['id' => $request_parameters[0]])->get();

$asset = $asset_response['payload']->toArray();

if ($asset) {
	$cash_admin->page_data = array_merge($cash_admin->page_data,$asset);
}

// Metadata shizz:
if (isset($cash_admin->page_data['metadata'])) {

	if (is_array($cash_admin->page_data['metadata'])) {
		foreach ($cash_admin->page_data['metadata'] as $key => $value) {
			$cash_admin->page_data['metadata_' . $key] = $value;
			if ($key == 'fulfillment' || $key == 'private') {
				$cash_admin->page_data['metadata_' . $key . '_json'] = json_encode($value);
			}
		}
	}
}

// Deal with tags:
$tag_counter = 1;
$tag_markup = '';
if (isset($asset['tags']) && is_array($asset['tags'])) {
	foreach ($asset['tags'] as $tag) {
		$tag_markup .= "<input type='text' name='tag$tag_counter' value='$tag' placeholder='Tag' />";
		$tag_counter = $tag_counter+1;
	}
}
$cash_admin->page_data['tag_counter'] = $tag_counter;
$cash_admin->page_data['tag_markup'] = $tag_markup;

// Reset page title to reflect the asset:
$cash_admin->page_data['ui_title'] = 'Edit “' . $cash_admin->page_data['title'] . '”';

// Code count
if ($asset_codes) {
	$cash_admin->page_data['asset_codes_count'] = count($asset_codes);
}

if ($cash_admin->page_data['type'] == 'file') {
	// parent id options markup:
	//$cash_admin->page_data['parent_options'] = '<option value="0" selected="selected">None</option>';

    $cash_admin->page_data['parent_options'] = "";

	$cash_admin->page_data['parent_options'] .= $admin_helper->echoFormOptions('assets',$cash_admin->page_data['parent_id'],$cash_admin->getAllFavoriteAssets(),true);
	// connection options markup:
	//$cash_admin->page_data['connection_options'] = '<option value="0" selected="selected">None (Normal http:// link)</option>';

    $cash_admin->page_data['connection_options'] = "";
	$cash_admin->page_data['connection_options'] .= $admin_helper->echoConnectionsOptions('assets', $cash_admin->page_data['connection_id'], true);

	if ($cash_admin->page_data['connection_id'] != 0) {
		$cash_admin->page_data['show_make_public'] = true;
	}

	$fulfillment_request = $admin_request->request('asset')
	                        ->action('getfulfillmentassets')
	                        ->with(['asset_details' => $request_parameters[0]])->get();

    if ($fulfillment_request['payload']) {
        $cash_admin->page_data['fulfillment_assets'] = new ArrayIterator($fulfillment_request['payload']);
    }

	// set the view
	$cash_admin->setPageContentTemplate('assets_details_file');
} else if ($cash_admin->page_data['type'] == 'release') {

	$fulfillment_response = $admin_request->request('asset')
	                        ->action('getfulfillmentassets')
	                        ->with(['asset_details' => $asset])->get();

	if ($fulfillment_response['payload']) {
		$cash_admin->page_data['fulfillment_files'] = new ArrayIterator($fulfillment_response['payload']);
	}

	if (isset($cash_admin->page_data['metadata']['private'])) {
		if (count($cash_admin->page_data['metadata']['private'])) {

			$private_response = $admin_request->request('asset')
			                        ->action('getfulfillmentassets')
			                        ->with([
                                        'asset_details' => $asset,
                                        'type' => 'private'
									])->get();

			if ($private_response['payload']) {
				$cash_admin->page_data['private_files'] = new ArrayIterator($private_response['payload']);
			}
		}
	}

	$cash_admin->page_data['cover_url'] = ADMIN_WWW_BASE_PATH . '/assets/images/release.jpg';

	if (isset($cash_admin->page_data['metadata']['cover'])) {
		if ($cash_admin->page_data['metadata']['cover']) { // effectively non-zero

			$cover_response = $admin_request->request('asset')
			                        ->action('getasset')
			                        ->with([
                                        'id' => $cash_admin->page_data['metadata']['cover']
									])->get();

			if ($cover_response['payload']) {
				$cover_asset = $cover_response['payload'];
				/*$cover_url_response = $cash_admin->requestAndStore(
					array(
						'cash_request_type' => 'asset',
						'cash_action' => 'getasseturl',
						'connection_id' => $cover_asset->connection_id,
						'user_id' => $admin_helper->getPersistentData('cash_effective_user'),
						'asset_location' => $cover_asset->location,
						'inline' => true
					)
				);*/
				if (isset($cover_asset->location)) {
					$cash_admin->page_data['cover_url'] = $cover_asset->location;
					$cash_admin->page_data['cover_asset_id'] = $cash_admin->page_data['metadata']['cover'];
				}
			}
		}
	}

	// set the view
	$cash_admin->setPageContentTemplate('assets_details_release');
} else {
	// default back to the most basic view:
	$cash_admin->page_data['form_state_action'] = 'doassetedit';
	$cash_admin->page_data['asset_button_text'] = 'Edit the asset';

	$cash_admin->setPageContentTemplate('assets_details');
}
?>
