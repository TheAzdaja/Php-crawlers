<?php
include($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');
require __DIR__ . "/vendor/autoload.php";
use Goutte\Client;

$client = new Client();
$product_url = strip_tags(trim($_POST['url']));
// $product_url = 'https://www.chainreactioncycles.com/us/en/vitus-substance-vrs-2-adventure-road-bike-2021/rp-prod195701';

$crawler = $client->request('GET', $product_url);
$script_string = $crawler->filterXPath('//script[contains(.,"mediaJSON")]')->text();

function get_string_part($string, $startStr, $endStr) {
    $startpos = strpos($string,$startStr);
    $endpos = strpos($string,$endStr,$startpos);
    $endpos = $endpos-$startpos;
    $string = substr($string,$startpos,$endpos);

    return $string;
}

function rename_file_path_if_exists(&$file_path, &$file_name, $counter) {
    
    if(file_exists(wp_get_upload_dir()['path'] . '/' . $file_name . '.jpg')) {
        if(substr($file_name, -2) === '-' . $counter) {
            $file_name = substr($file_name, 0, strlen($file_name) - 2);
        } else if(substr($file_name, -3) === '-' . $counter) {
            $file_name = substr($file_name, 0, strlen($file_name) - 3);
        }
        $counter++;
        $file_path = $_SERVER['DOCUMENT_ROOT'] . '/' . 'wp-content/uploads/' . $file_name . '-' . $counter . '.jpg';
        $file_name = $file_name . '-' . $counter;
        rename_file_path_if_exists($file_path, $file_name, $counter);
    }
}

$trimmed_string = get_string_part($script_string, '"explicitMixedMediaSet":"', '"id"');
$trimmed_string = substr(get_string_part($trimmed_string, 'Chain', '",'), 0, -1);
$trimmed_array = explode(";,", $trimmed_string . '?wid=960');

$product_data = array();

function velo_insert_attachment($urls, $parent_post_id = null) {

    if( !class_exists( 'WP_Http' ) )
        include_once( ABSPATH . WPINC . '/class-http.php' );

    $http = new WP_Http();
    $images = [];

    foreach($urls as $url) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        $url = 'https://media.chainreactioncycles.com/is/image/' . $url;
        $requst_args['headers'] = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36',
        ];
        $response = $http->request( $url, $requst_args );

        if( $response['response']['code'] != 200 ) {
            return false;
        }

        $file_name = substr(str_replace('%20', '-', basename($url)), 0, -8);
        $file_path = $_SERVER['DOCUMENT_ROOT'] . '/' . 'wp-content/uploads/' . $file_name . '.jpg';

        rename_file_path_if_exists($file_path, $file_name, 1);

        $ch = curl_init($url);
        $fp = fopen($file_path, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        $file_type = 'image/jpeg';
        $attachment_title = sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) );
        $wp_upload_dir = wp_upload_dir();
    
        $post_info = array(
            'guid'           => $wp_upload_dir['url'] . '/' . $file_name . '.jpg',
            'post_mime_type' => $file_type,
            'post_title'     => $attachment_title,
            'post_content'   => '',
            'post_status'    => 'inherit',
        );
    
        // Create the attachment
        $attach_id = wp_insert_attachment( $post_info, $file_path, 0 );
    
        // Define attachment metadata
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path, 0 );
    
        // Assign metadata to attachment
        wp_update_attachment_metadata( $attach_id,  $attach_data );

        array_push($images, $attach_id);
    }

    return $images;
}

$product_data['title']= $crawler->filter('.crcPDPTitle > h1')->text('');

if($crawler->filter('.discount')->text("")) {
    $price_high = filter_var( $crawler->filter('.crcPDPPriceRRP .value')->text(), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
    $price_low = $crawler->filter('.crcPDPPriceHidden')->text();
} else {
    $price_high = $crawler->filter('.crcPDPPriceHidden')->text();
    $price_low = "";
}

$product_data['price_high'] = $price_high;
$product_data['price_low'] = $price_low;

$description = $crawler->filter('.crcPDPDescription p')->each(function($node) {
    return $node->text();
});

if($description) {
    $product_data['desc_short'] = $description[0];
} else {
    $product_data['desc_short'] = '';
}

$product_data['desc_long'] = '';
for($i = 0; $i < count($description); $i++) {
    if ($i !== 0) {
        $product_data['desc_long'] .= $description[$i];
    }
}

$product_data['tech_specs'] = '';
if($crawler->filter('.crcPDPDescription ul')) {
    $tech_specs = $crawler->filter('.crcPDPDescription ul li')->each(function($node) {
        return trim(preg_replace('/\s\s+/', ' ', $node->text()));
    });
    for($i = 0; $i < count($tech_specs); $i++) {
        $tech_specs[$i] = $i % 2 === 0 ? $tech_specs[$i] . "\n" : $tech_specs[$i];
        $product_data['tech_specs'] .= $tech_specs[$i];
    }
}

if($crawler->filter('ul.crcPDPList')) {
    $tech_specs = $crawler->filter('ul.crcPDPList li')->each(function($node) {
        return trim(preg_replace('/\s\s+/', ' ', $node->text()));
    });
    for($i = 0; $i < count($tech_specs); $i++) {
        $tech_specs[$i] = $i % 2 === 0 ? $tech_specs[$i] . "\n" : $tech_specs[$i];
        $product_data['tech_specs'] .= $tech_specs[$i];
    }
}

if(count($trimmed_array) > 4) {
    $trimmed_array = array_slice($trimmed_array, 0, 5, true);
}

$product_images = velo_insert_attachment($trimmed_array);

$product_data['images_ids'] = implode(',',$product_images);

$images_sources = [];
foreach($product_images as $image) {
  $image_element = wp_get_attachment_image_src($image, 'full');
  array_push($images_sources, $image_element[0]);
}

$product_data['images_urls'] = $images_sources;

echo json_encode($product_data);

?>