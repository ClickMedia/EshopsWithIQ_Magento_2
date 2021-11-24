<?php
namespace InnovateOne\EshopsWithIQ\Model;

class Helper
{
	public function call($url, $post_data) {
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$post_data['ip'] = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$post_data['ip'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$post_data['ip'] = $_SERVER['REMOTE_ADDR'];
		}
		$post_data['browser'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$post_data['url'] = $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		$post_data['referrer'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		$post_data = json_encode($post_data);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, ['data' => $post_data]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 2);
		$response = curl_exec($ch);
		if (isset($_GET['eswiq_debug'])) {
			if(curl_error($ch)){
				$errors[] = 'Error: "' . curl_error($ch) . '" - Code: ' . curl_errno($ch);
				print('ERRORS: '.print_r($errors, true));
			} else {
				print('REQUEST: '.print_r($post_data, true).', <br>RESPONSE: '.$response);
			}
		}
		
		curl_close($ch);
		
		return $response;
	}
}