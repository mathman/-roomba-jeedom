<?php

function roombaRequest($request) {
	log::add('roomba', 'debug', "request: " . $request);

    //Initialize cURL.
    $ch = curl_init();
    
    //Set the URL that you want to GET by using the CURLOPT_URL option.
    curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:8081/" . $request);

    //Set CURLOPT_RETURNTRANSFER so that the content is returned as a variable.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    //Execute the request.
    $data = curl_exec($ch);
    
    //Close the cURL handle.
    curl_close($ch);
    
    log::add('roomba', 'debug', "response: " . $data);

    return json_decode($data);
}

?>
