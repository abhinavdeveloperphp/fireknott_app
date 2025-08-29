<?php
use Picqer\Barcode\BarcodeGeneratorPNG;
function shopify_call($token, $shop, $api_endpoint, $query = array(), $method = 'GET', $request_headers = array())
{

	// Build URL
	$url = "https://" . $shop . ".myshopify.com" . $api_endpoint;
	if (!is_null($query) && in_array($method, array('GET', 'DELETE')))
		$url = $url . "?" . http_build_query($query);

	// Configure cURL
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_HEADER, TRUE);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($curl, CURLOPT_MAXREDIRS, 3);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
	// curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 3);
	// curl_setopt($curl, CURLOPT_SSLVERSION, 3);
	curl_setopt($curl, CURLOPT_USERAGENT, 'My New Shopify App v.1');
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($curl, CURLOPT_TIMEOUT, 30);
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

	// Setup headers
	$request_headers[] = "";
	if (!is_null($token))
		$request_headers[] = "X-Shopify-Access-Token: " . $token;
	curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);

	if ($method != 'GET' && in_array($method, array('POST', 'PUT'))) {
		if (is_array($query))
			$query = http_build_query($query);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
	}

	// Send request to Shopify and capture any errors
	$response = curl_exec($curl);
	$error_number = curl_errno($curl);
	$error_message = curl_error($curl);

	// Close cURL to be nice
	curl_close($curl);

	// Return an error is cURL has a problem
	if ($error_number) {
		return $error_message;
	} else {

		// No error, return Shopify's response by parsing out the body and the headers
		$response = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);

		// Convert headers into an array
		$headers = array();
		$header_data = explode("\n", $response[0]);
		$headers['status'] = $header_data[0]; // Does not contain a key, have to explicitly set
		array_shift($header_data); // Remove status, we've already set it above
		foreach ($header_data as $part) {
			$h = explode(":", $part);
			$headers[trim($h[0])] = trim($h[1]);
		}

		// Return headers and Shopify's response
		return array('headers' => $headers, 'response' => $response[1]);

	}

}




function shopify_gql_call($token, $shop, $query = array())
{

	// Build URL
	$url = "https://" . $shop . "/admin/api/2021-10/graphql.json";


	// Configure cURL
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_HEADER, TRUE);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($curl, CURLOPT_MAXREDIRS, 3);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);


	// Setup headers
	$request_headers[] = "";
	$request_headers[] = "Content-Type: application/json";
	if (!is_null($token))
		$request_headers[] = "X-Shopify-Access-Token: " . $token;
	curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);
	curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($query));
	curl_setopt($curl, CURLOPT_POST, true);



	// Send request to Shopify and capture any errors
	$response = curl_exec($curl);
	$error_number = curl_errno($curl);
	$error_message = curl_error($curl);

	// Close cURL to be nice
	curl_close($curl);

	// Return an error is cURL has a problem
	if ($error_number) {
		return $error_message;
	} else {

		// No error, return Shopify's response by parsing out the body and the headers
		$response = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);

		// Convert headers into an array
		$headers = array();
		$header_data = explode("\n", $response[0]);
		$headers['status'] = $header_data[0]; // Does not contain a key, have to explicitly set
		array_shift($header_data); // Remove status, we've already set it above
		foreach ($header_data as $part) {
			$h = explode(":", $part);
			$headers[trim($h[0])] = trim($h[1]);
		}

		// Return headers and Shopify's response
		return array('headers' => $headers, 'response' => $response[1]);

	}

}

function generateLabelImage($brand, $mrp, $productType, $code, $size, $color, $website, $savePath)
{
	$width = 900;
	$height = 200;
	$im = imagecreatetruecolor($width, $height);

	// Colors
	$white = imagecolorallocate($im, 255, 255, 255);
	$black = imagecolorallocate($im, 0, 0, 0);

	// Fill background
	imagefill($im, 0, 0, $white);

	// ✅ Barcode should ONLY contain product code
	$generator = new BarcodeGeneratorPNG();
	$barcodeContent = "$brand|$mrp|$code|$size|$color";
	$barcodeData = $generator->getBarcode($barcodeContent, $generator::TYPE_CODE_128, 2.5, 80);
	$barcodeImg = imagecreatefromstring($barcodeData);

	// ✅ Brand Logo
	$logoPath = __DIR__ . "/logo.png";
	if (file_exists($logoPath)) {
		$logoImg = imagecreatefrompng($logoPath);
		$logoW = imagesx($logoImg);
		$logoH = imagesy($logoImg);
		$newW = 120;
		$newH = intval(($logoH / $logoW) * $newW);
		$resizedLogo = imagecreatetruecolor($newW, $newH);
		imagealphablending($resizedLogo, false);
		imagesavealpha($resizedLogo, true);
		imagecopyresampled($resizedLogo, $logoImg, 0, 0, 0, 0, $newW, $newH, $logoW, $logoH);
		$x = ($width - $newW) / 2;
		$y = 5;
		imagecopy($im, $resizedLogo, $x, $y, 0, 0, $newW, $newH);
		imagedestroy($logoImg);
		imagedestroy($resizedLogo);
	} else {
		imagestring($im, 5, 150, 10, $brand, $black);
	}

	// MRP
	imagestring($im, 4, 10, 50, "MRP Rs." . $mrp, $black);

	// Product type bottom-left
	imagestring($im, 4, 10, $height - 20, $productType, $black);

	// Place barcode centered
	$barcodeW = imagesx($barcodeImg);
	$barcodeH = imagesy($barcodeImg);
	$x = ($width - $barcodeW) / 2;
	$y = 70;
	imagecopy($im, $barcodeImg, $x, $y, 0, 0, $barcodeW, $barcodeH);

	// Extra info under barcode
	imagestring($im, 3, 80, 160, "$code  Size-$size  $color", $black);

	// Website on right (vertical)
	imagestringup($im, 3, $width - 15, 140, $website, $black);

	// Save file
	imagepng($im, $savePath);

	imagedestroy($im);
	imagedestroy($barcodeImg);
}




