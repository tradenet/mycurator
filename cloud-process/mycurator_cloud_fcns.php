<?php
//mycurator_cloud_fcns - a set of functions to include for cloud processing that are shared between the cloud process and the page worker process
//
//
function mct_ai_call_diffbot($url, $topic, $dbot_token = '') {
    //This function performs the call to Diffbot API V3 to render a page and handles errors
    global $token_ind, $dblink;
    
    mct_cs_log($topic['topic_name'], MCT_AI_LOG_ACTIVITY, 'DiffBot call started', $url);
    
    // Encode URL for DiffBot API - diffbot_UrlEncode does the encoding, don't use rawurlencode first
    $dbot_url = diffbot_UrlEncode($url);
    
    // Use provided token parameter if available
    // Otherwise use admin-configured constants from mycurator_cloud_init.php
    if (empty($dbot_token)) {
        // Try admin-configured tokens first (primary method)
        // Use whichever token is configured (either Business OR Individual, not both)
        if (defined('DIFFBOT_TOKEN_BUSINESS') && !empty(DIFFBOT_TOKEN_BUSINESS)) {
            $dbot_token = DIFFBOT_TOKEN_BUSINESS;
            mct_cs_log($topic['topic_name'], MCT_AI_LOG_ACTIVITY, 'Using BUSINESS token', $url);
        } elseif (defined('DIFFBOT_TOKEN_INDIVIDUAL') && !empty(DIFFBOT_TOKEN_INDIVIDUAL)) {
            $dbot_token = DIFFBOT_TOKEN_INDIVIDUAL;
            mct_cs_log($topic['topic_name'], MCT_AI_LOG_ACTIVITY, 'Using INDIVIDUAL token', $url);
        }
        
        if (empty($dbot_token)) {
            $business_status = defined('DIFFBOT_TOKEN_BUSINESS') ? (empty(DIFFBOT_TOKEN_BUSINESS) ? 'empty' : 'set') : 'undefined';
            $individual_status = defined('DIFFBOT_TOKEN_INDIVIDUAL') ? (empty(DIFFBOT_TOKEN_INDIVIDUAL) ? 'empty' : 'set') : 'undefined';
            mct_cs_log($topic['topic_name'], MCT_AI_LOG_ERROR, "No token: BUSINESS=$business_status INDIVIDUAL=$individual_status", $url);
            return '';
        }
    } else {
        mct_cs_log($topic['topic_name'], MCT_AI_LOG_ACTIVITY, 'Using user-provided token', $url);
    }

    $dbot = 'http://api.diffbot.com/v3/article?token='.$dbot_token.'&url='.$dbot_url.'&fields=html,title,date,author,resolvedPageUrl,images(*),videos(*)';
    
    mct_cs_log($topic['topic_name'], MCT_AI_LOG_ACTIVITY, 'Calling DiffBot API', $url);
    // Debug: log the actual API URL (without full token for security)
    $debug_url = str_replace($dbot_token, substr($dbot_token, 0, 8).'...', $dbot);
    mct_cs_log($topic['topic_name'], MCT_AI_LOG_ACTIVITY, 'API URL: '.$debug_url, '');
    
    // INIT CURL
    $ch = curl_init();
    if ($ch === false) {
        mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, 'CURL init failed',$url);
        return '';
    }
    
    // SET URL
    curl_setopt($ch, CURLOPT_URL, $dbot);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,10);
    curl_setopt($ch,CURLOPT_TIMEOUT,40);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    
    //and go
    $content = curl_exec ($ch);
    
    // Check for curl errors first
    if ($content === false) {
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, "CURL error: [$curl_errno] $curl_error",$url);
        curl_close($ch);
        return '';
    }
    
    //check results
    $info = curl_getinfo($ch);
    $response_code = $info['http_code'];
    $content_length = strlen($content);
    
    mct_cs_log($topic['topic_name'], MCT_AI_LOG_ACTIVITY, "DiffBot HTTP $response_code ($content_length bytes)", $url);
    
    if ($info['http_code'] != 200) {
        //log error with more detail        
        if (empty($content)){
            mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, 'HTTP '.$info['http_code'].' (empty response)',$url);
        } else {
            $json = json_decode($content);
            if (!empty($json)) {
                $error_msg = isset($json->error) ? $json->error : 'Unknown error';
                $error_code = isset($json->errorCode) ? " Code:{$json->errorCode}" : '';
                // Log full response for debugging if error is unclear
                if ($error_msg == 'Unknown error') {
                    mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, 'DiffBot response: '.substr($content,0,200),$url);
                }
                mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, 'DiffBot error: '.$error_msg.$error_code,$url);
            } else { 
                $con = substr($content,0,200);
                mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, 'HTTP '.$info['http_code'].': '.$con,$url);
            }
        }
        curl_close($ch);
        return '';
    }
    curl_close ($ch); 
    $itm = json_decode($content);
    if (!empty($itm->error)) {
        mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, 'Diffbot Error '.$itm->error,$url);
        return '';
    }
    
    // Log DiffBot response for debugging
    if (empty($itm->objects) || !is_array($itm->objects) || count($itm->objects) == 0) {
        mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, 'DiffBot returned no objects. Response: '.substr($content, 0, 200),$url);
        return '';
    }
    
    $page = diffbot_page($itm, $topic, $url);
    if (empty($page)) {
        mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, 'Error Rendering Page',$url);
    }
    return $page;
}

function diffbot_page($fullitm, $topic = null, $url = ''){
    $article = '';
    $itm = $fullitm->objects[0]; //Get the objects (only one for article API V3)
    
    // Check if required content fields exist
    $has_html = isset($itm->html) && !empty($itm->html);
    $has_text = isset($itm->text) && !empty($itm->text);
    $has_images = isset($itm->images) && !empty($itm->images);
    $has_videos = isset($itm->videos) && !empty($itm->videos);
    
    // Log what we received
    if (!$has_html && !$has_text && !$has_images && !$has_videos && $topic) {
        $fields = 'Fields present: ';
        foreach ($itm as $key => $val) {
            $fields .= $key . ', ';
        }
        mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, 'DiffBot returned no content. ' . $fields, $url);
    }
    
    if (!$has_html) {
        if ($has_text) {
            $article = $itm->text;
            $article = preg_replace('/(\r?\n|\r)/', '</p><p>', $article);
	    $article = '<p>' . str_replace('<p></p>', '', $article) . '</p>';
        }
        else {
            if (!$has_images && !$has_videos){
                return '';  //page not rendered into text or media
            }
        }
    }
    else {
        $article = $itm->html;
    }

    $images = '';
    if (!empty($itm->images)) {
        foreach ($itm->images as $media){
            if (stripos($article,$media->url) !== false){
                continue;  //dup so skip it
            }
            if (stripos($images,$media->url) !== false){
                continue;  //dup so skip it
            }
            if (isset($media->url)) {
                $images .= '<img id="side_image" src="'.$media->url.'">';
            }
        }
    }
    if (isset($itm->videos) && !empty($itm->videos)) {
        foreach ($itm->videos as $media) {
            if (stripos($article,$media->url) !== false){
                continue;  //dup so skip it
            }
            if (stripos($images,$media->url) !== false){
                continue;  //dup so skip it
            }
            if (isset($media->url)) {
                $images .= '<iframe title="Video Player" class="youtube-player" type="text/html" ';
                $images .= 'width="250" height="250" src="'.$media->url.'"';
                $images .= 'frameborder="0" allowFullScreen></iframe>';
            }
        }
    }
    if (!empty($images)){
        $images = '<div id="box_media">'.$images.'</div>';  //add a div
    }
    //Get best source url
    $src_url = '';
    if (isset($itm->resolvedPageUrl) && !empty($itm->resolvedPageUrl)) {
        $src_url = $itm->resolvedPageUrl;
    } elseif (isset($itm->pageUrl) && !empty($itm->pageUrl)) {
        $src_url = $itm->pageUrl;
    }
    
    if (empty($src_url)) {
        $src_url = $url; // Fallback to original URL
    }
    //Remove Script Tags
    $article = preg_replace( '@<script[^>]*?>.*?</script>@si', '', $article );
    //Build HTML for page
    $pageContent = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
    $pageContent .= '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">';
    $pageContent .= '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    //Check if no text and set up for a redirect
    $txt_chk = wp_strip_all_tags($article);
    $txt_chk = preg_replace('/\s\s+/', '',$txt_chk);  //remove all white space, even spaces between words
    if(strlen($txt_chk) < 10){
        $pageContent .= '<!--Redirect{'.$src_url.'}-->';
    }
    $tstr = (empty($itm->title)) ? 'No Title' : $itm->title;
    $pageContent .= '<title>'.$tstr.'</title></head>';
    $pageContent .= '<body><div id="savelink-article">';
    $pageContent .= '<h1 id="sl-title">'.$tstr.'</h1>';
    if (!empty($itm->author) || !empty($itm->date)){
        $pageContent .= '<div id="savelink-author">';
        if (!empty($itm->author)) $pageContent .= 'Author: '.$itm->author.'&nbsp;&nbsp;';
        if (!empty($itm->date)) {
            $dstr = preg_replace('@\d\d:\d\d:\d\d GMT@','',$itm->date);
            $pageContent .= 'Date: '.$dstr;
        }
        $pageContent .= '</div>';
    }
    $hoststr = parse_url($src_url,PHP_URL_HOST);
    $pageContent .= '<div id="source-url"><a href="'.$src_url.'" >Click here to view original web page at '.$hoststr.'</a></div>';
    if ($images != ''){
        $pageContent .= $images;
    }
    $pageContent .= '<span class="mct-ai-article-content">'.$article.'</span>';
    $pageContent .= '</div></body></html>';

    return $pageContent;
}

function diffbot_page_v1($itm){
    $article = '';
    if ($itm->{'html'} == '') {
        if ($itm->{'text'} != '') {
            $article = $itm->{'text'};
            $article = preg_replace('/(\r?\n|\r)/', '</p><p>', $article);
	    $article = '<p>' . str_replace('<p></p>', '', $article) . '</p>';
        }
        else {
            if ($itm->{'media'} == ''){
                return '';  //page not rendered into text or media
            }
        }
    }
    else {
        $article = $itm->{'html'};
    }

    $images = '';
    if ($itm->{'media'} != '') {
        $images = '<div id="box_media">';
        foreach ($itm->{'media'} as $media){
            if (stripos($article,$media->{'link'}) !== false){
                continue;  //dup so skip it
            }
            if (stripos($images,$media->{'link'}) !== false){
                continue;  //dup so skip it
            }
            if (stripos($media->{'link'},'wsj.net') !== false){
                //Duplicate images with _D_ and _G_ values
                if (stripos($media->{'link'},'_G_') !== false){
                    continue;
                }
            }
            if ($media->{'type'} == 'video'){
                $images .= '<iframe title="Video Player" class="youtube-player" type="text/html" ';
                $images .= 'width="250" height="250" src="'.$media->{'link'}.'"';
                $images .= 'frameborder="0" allowFullScreen></iframe>';
            }
            else {
                $images .= '<img id="side_image" src="'.$media->{'link'}.'">';
            }
        }
        $images .= '</div>';
    }
    //Remove Script Tags
    $article = preg_replace( '@<script[^>]*?>.*?</script>@si', '', $article );
    //Build HTML for page
    $pageContent = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
    $pageContent .= '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">';
    $pageContent .= '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    $pageContent .= '<link rel="stylesheet" type="text/css" href="mct_ai_local_style" />';
    //Check if no text and set up for a redirect
    $txt_chk = wp_strip_all_tags($article);
    $txt_chk = preg_replace('/\s\s+/', '',$txt_chk);  //remove all white space, even spaces between words
    if(strlen($txt_chk) < 10){
        $pageContent .= '<!--Redirect{'.$itm->{'url'}.'}-->';
    }
    $pageContent .= '<title>'.$itm->{'title'}.'</title></head>';
    $pageContent .= '<body><div id="savelink-article">';
    $pageContent .= '<h1 id="sl-title">'.$itm->{'title'}.'</h1>';
    if ($itm->{'author'}){
        $pageContent .= '<div>Author: '.$itm->{'author'}.'</div>';
    }
    $hoststr = parse_url($itm->{'url'},PHP_URL_HOST);
    $pageContent .= '<div id="source-url"><a href="'.$itm->{'url'}.'" >Click here to view original web page at '.$hoststr.'</a></div>';
    //$pageContent .= '<div>';
    if ($images != ''){
        $pageContent .= $images;
    }
    $pageContent .= '<span class="mct-ai-article-content">'.$article.'</span>';
    $pageContent .= '</div></body></html>';
    $a = 'b';
    return $pageContent;
}

/**
 * Properly strip all HTML tags including script and style
 *
 * @since 2.9.0
 *
 * @param string $string String containing HTML tags
 * @param bool $remove_breaks optional Whether to remove left over line breaks and white space chars
 * @return string The processed string.
 */
function wp_strip_all_tags($string, $remove_breaks = false) {
	$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
	$string = strip_tags($string);

	if ( $remove_breaks )
		$string = preg_replace('/[\r\n\t ]+/', ' ', $string);

	return trim($string);
}

function diffbot_UrlEncode($string) {
    $string = str_replace('%','%25',$string);  // Fixed: was missing assignment
    $replacements = array('%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%23', '%5B', '%5D');
    $entities = array('!', '*', "'", "(", ")", ";", ":", "@", "&", "=", "+", "$", ",", "/", "?", "#", "[", "]");
    return str_replace($entities, $replacements, $string);
}


?>
