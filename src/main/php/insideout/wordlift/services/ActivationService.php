<?php

class WordLift_ActivationService {

	public $logger;
	public $url;

	public function activate() {

		$this->logger->trace( "Activating the WordLift Plugin..." );

		$data = array(
			"url" => $this->getUrl()
		);

		// use key 'http' even if you send the request to https://...
		$options = array('http' =>
						array(
							'method'  => 'POST',
							'content' => json_encode( $data ),
							'header'=>  "Content-Type: application/json\r\n" .
										"Accept: application/json\r\n"
						)
					);
		$context  = stream_context_create($options);
		$result = file_get_contents( $this->url, false, $context );

		$json = json_decode( $result );

		if ( property_exists( $json, "key") )
			$siteKey = $json->key;
		else
			$siteKey = $this->getSiteKey();			

		if ( NULL != $siteKey )
			add_option( "wordlift_site_key", $siteKey );
	}

	private function getUrl() {
		return admin_url( "options-general.php?page=wordlift_settings" );
	}

	private function getSiteKey() {

		$url = $this->url . "?url=" . urlencode( $this->getUrl() );

		// use key 'http' even if you send the request to https://...
		$options = array('http' =>
						array(
							'method'  => 'GET',
							'header'=>  "Content-Type: application/json\r\n" .
										"Accept: application/json\r\n"
						)
					);
		$context  = stream_context_create($options);
		$result = file_get_contents( $url, false, $context );

		$json = json_decode( $result );

		if ( property_exists( $json, "key") )
			return $json->key;
		else
			return NULL;

	}

}

?>