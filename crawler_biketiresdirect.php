<?php
include($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
require __DIR__ . "/vendor/autoload.php";

use Goutte\Client;

$client = new Client();
$product_url = strip_tags(trim($_POST['url']));

$crawler = $client->request('GET', $product_url);
// $images = $crawler->filter('#photos > div.swiper-container.mainImage > div.swiper-wrapper > div:nth-child(4) > img')->each(function ($node, $i) {
//     return $node->attr('src');
// });
$images = $crawler->filterXPath('//*[@id="photos"]/div[1]/div[1]/div[2]/img')->attr('src');
// Pronaci sve slike za odredjeni proizvod ali ne preko xPatha
function get_string_part($string, $startStr, $endStr)
{
    $startpos = strpos($string, $startStr);
    $endpos = strpos($string, $endStr, $startpos);
    $endpos = $endpos - $startpos;
    $string = substr($string, $startpos, $endpos);

    return $string;
}

function rename_file_path_if_exists(&$file_path, &$file_name, $counter)
{

    if (file_exists(wp_get_upload_dir()['path'] . '/' . $file_name . '.jpg')) {
        if (substr($file_name, -2) === '-' . $counter) {
            $file_name = substr($file_name, 0, strlen($file_name) - 2);
        } else if (substr($file_name, -3) === '-' . $counter) {
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

function velo_insert_attachment($urls, $parent_post_id = null)
{
    // echo "<script>console.log('Debug IMAGES: " . $urls . "' );</script>";
    if (!class_exists('WP_Http'))
        include_once(ABSPATH . WPINC . '/class-http.php');

    $http = new WP_Http();
    $images = [];

    foreach ($urls as $url) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        $url = 'https:' . $url;
        $requst_args['headers'] = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36',
        ];
        // echo "<script>console.log('Debug Objects: " . $url . "' );</script>";
        $response = $http->request($url, $requst_args);

        if ($response['response']['code'] != 200) {
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
        $attachment_title = sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME));
        $wp_upload_dir = wp_upload_dir();

        $post_info = array(
            'guid'           => $wp_upload_dir['url'] . '/' . $file_name . '.jpg',
            'post_mime_type' => $file_type,
            'post_title'     => $attachment_title,
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        // Create the attachment
        $attach_id = wp_insert_attachment($post_info, $file_path, 0);

        // Define attachment metadata
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path, 0);

        // Assign metadata to attachment
        wp_update_attachment_metadata($attach_id,  $attach_data);

        array_push($images, $attach_id);
    }

    return $images;
}

if (count($images) > 4) {
    $images = array_slice($images, 0, 5, true);
}

$product_images = velo_insert_attachment($images);

$product_data['images_ids'] = implode(',', $product_images);

$images_sources = [];
foreach ($product_images as $image) {
    $image_element = wp_get_attachment_image_src($image, 'full');
    array_push($images_sources, $image_element[0]);
}

$product_data['images_urls'] = $images_sources;




$product_data['title'] = $crawler->filter('h1')->text();


if (($crawler->filter('span#pd_msrp')->text(""))) {
    $price_high = filter_var($crawler->filter('span#pd_msrp')->text(), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
    $price_low = filter_var($crawler->filter('span#pd_price')->text(), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
} else {
    $price_high = filter_var($crawler->filter('span#pd_price')->text(), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $price_low = "";
}


$value = $price_high;
if ($value < 0) {
    $value = $value * -1;
}

$product_data['price_high'] = ("$value");
$product_data['price_low'] = ($price_low);

// if($crawler->filter('span#pd_price')->text("")) {
//     $price_high = filter_var( $crawler->filter('span#pd_msrp')->text(), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
//     $price_low = $crawler->filter('span#pd_price')->text();
// } else {
//     $price_high = $crawler->filter('span#pd_price')->text();
//     $price_low = "";
// }

// $product_data['price_high'] = $price_high;
// $product_data['price_low'] = $price_low;

// $product_data['desc_short']  = '';
// if ($crawler->filter('ul.product-desc__bulletpoint')) {
//     $desc_short = $crawler->filter('li.product-desc__bulletpoint')->each(function ($node) {
//         return trim(preg_replace('/\s\s+/', ' ', $node->text()));
//     });
//     for ($i = 0; $i < count($desc_short); $i++) {
//         $desc_short[$i] = $i % 1 === 0 ? $desc_short[$i]."," . "\n" : $desc_short[$i];
//         $product_data['desc_short'] .= $desc_short[$i];
//     }
// }


$description = $crawler->filter('.ts_pd_body p:nth-of-type(n+2)')->each(function($node) {
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
        $product_data['desc_long'] .= $description[$i-3];
    }
}

// $product_data['desc_long']  = '';
// if ($crawler->filter('div.tabWindowSelected')) {
//     $desc_long = $crawler->filter('div.tabWindowSelected p')->each(function ($node) {
//         return trim(preg_replace('/\s\s+/', ' ', $node->text()));
//     });
//     for ($i = 0; $i < count($desc_long); $i++) {
//         $desc_long[$i] = $i % 1 === 0 ? $desc_long[$i] . "\n" : $desc_long[$i];
//         $product_data['desc_long'] .= $desc_long[$i-3];
//     }
// }

// $product_data['desc_short']  = '';
// if($crawler->filter('tr')) {
//     $desc_short = $crawler->filter('td')->each(function($node) {
//         return trim(preg_replace('/\s\s+/', ' ', $node->text()));
//     });
//     for($i = 0; $i < count($desc_short); $i++) {
//         $desc_short[$i] = $i % 1 === 0 ? $desc_short[$i] . "\n" : $desc_short[$i];
//         $product_data['desc_short'] .= $desc_short[$i] ;
//     }
  
// }

// $product_data['desc_long'] =  $crawler->filter('div.product-desc p')->each(function ($node) {
//     return $node->text() . " ";
// });

// $product_data['desc_long']  = '';
// if ($crawler->filter('div.product-desc')) {
//     $desc_long = $crawler->filter('div.product-desc p')->each(function ($node) {
//         return trim(preg_replace('/\s\s+/', ' ', $node->text()));
//     });
//     for ($i = 0; $i < count($desc_long); $i++) {
//         $desc_long[$i] = $i % 1 === 0 ? $desc_long[$i] . "\n" : $desc_long[$i];
//         $product_data['desc_long'] .= $desc_long[$i];
//     }
// }

// $product_data['desc_long'] =  $crawler->filter('div.product-desc p')->each(function ($node) {
//     return $node->text() . " ";
// });



$product_data['tech_specs']  = '';
if($crawler->filter('.ts_pd_body ul')) {
    $tech_specs = $crawler->filter('.ts_pd_body li')->each(function($node) {
        return trim(preg_replace('/\s\s+/', ' ', $node->text()));
    });
    for($i = 0; $i < count($tech_specs); $i++) {
        $tech_specs[$i] = $i % 1 === 0 ? $tech_specs[$i] . "\n" : $tech_specs[$i];
        $product_data['tech_specs'] .= $tech_specs[$i] ;
    }
}



echo json_encode($product_data);
