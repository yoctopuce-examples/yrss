<?php
include('Sources/yocto_api.php');
include('Sources/yocto_display.php');


define('LOG_FILE_PATH','log.txt');
// $RSS_FEEDS_URL : put your RSS feed URL into this array
$RSS_FEEDS_URL = array(
    "http://www.yoctopuce.com/EN/rss.xml"
);
// NB_FEED_ITEM_TO_SHOW : how may item of the feed to show
define("NB_FEED_ITEM_TO_SHOW",0);

//nb millisecond for the transition
define('TRANSITION_DURATION',500);
//nb millisecond for the transition
define('DISPLAY_DURATION',20000);


//helper function that will save message into the 
// log file LOG_FILE_PATH
function LogToFile($message)
{
	
    $lines=explode("\n", $message);
    foreach($lines as $l){
        // Write the contents to the file, 
        // using the FILE_APPEND flag to append the content to the end of the file
        // and the LOCK_EX flag to prevent anyone else writing to the file at the same time
        $towrite = "[".date("c",time())."]:$l<br>\n";
    	file_put_contents(LOG_FILE_PATH, $towrite, FILE_APPEND | LOCK_EX);
    }
}

// display an item on a MaxiDisplay 
function OutputMaxiDisplay($display, $item)
{
	$w = $display->get_DisplayWidth();
	$h = $display->get_DisplayHeight();
	//clear background layer
	$layer1 = $display->get_displayLayer(1);
	$layer1->clear();
	//move current layer into the background (layer 2 -> layer 1)
	$display->swapLayerContent(1,2);
	// from now work on 
	$layer2 = $display->get_displayLayer(2);
	$layer2->clear();
	$layer2->selectGrayPen(0);
	$layer2->drawBar(0,0,$w-1,$h-1);
	$layer2->selectGrayPen(255);
	$layer2->setLayerPosition($w,$h,0);
	$layer2->selectFont('Small.yfm');
	$layer2->drawText(0,0,Y_ALIGN_TOP_LEFT,$item['feed']);
	$layer2->drawText($w-1,0,Y_ALIGN_TOP_RIGHT,date("j M",$item['date']));
  	$layer2->moveTo(0,9);
  	$layer2->lineTo($w-1,9);
	$layer2->setConsoleMargins(0,11,$w-1,$h-1);
	$layer2->setConsoleBackground(0);
	$layer2->setconsoleWordWrap(true);
	$layer2->consoleOut($item['title']);
	$layer2->setLayerPosition(0,0,TRANSITION_DURATION);
	$display->pauseSequence(TRANSITION_DURATION+DISPLAY_DURATION);
}

// display an item on a MaxiDisplay 
function OutputMiniDisplay($display, $item)
{
	$w = $display->get_DisplayWidth();
	$h = $display->get_DisplayHeight();
	//clear background layer
	$layer1 = $display->get_displayLayer(1);
	$layer1->clear();
	//move current layer into the background (layer 2 -> layer 1)
	$display->swapLayerContent(1,2);
	// from now work on 
	$layer2 = $display->get_displayLayer(2);
	$layer2->clear();
	$layer2->selectGrayPen(0);
	$layer2->drawBar(0,0,$w-1,$h-1);
	$layer2->selectGrayPen(255);
	$layer2->setLayerPosition($w,$h,0);
	$layer2->selectFont('Small.yfm');
	$layer2->drawText(0,0,Y_ALIGN_TOP_LEFT,$item['feed']);
	$layer2->drawText($w-1,0,Y_ALIGN_TOP_RIGHT,date("j M",$item['date']));
	$layer2->drawText(0,8,Y_ALIGN_TOP_LEFT,$item['title']);
	$layer2->setLayerPosition(0,0,TRANSITION_DURATION);
	$display->pauseSequence(TRANSITION_DURATION+DISPLAY_DURATION);
}


// Use explicit error handling rather than exceptions
yDisableExceptions();

// Setup the API to use the VirtualHub on local machine
$errmsg = "";
if(yRegisterHub('callback',$errmsg) != YAPI_SUCCESS) {
	logtofile("Unable to start the API in callback mode ($errmsg)");
	die();		
}



$RSSItems = array();
foreach ($RSS_FEEDS_URL as $rssfeedurl) {
    if(!@$rssfeed=simplexml_load_file($rssfeedurl)){
        LogToFile("Unable to load the feed ".$rssfeedurl);
        continue;
    }
    if(empty($rssfeed->channel->title) || empty($rssfeed->channel->description) || empty($rssfeed->channel->item->title)){
        LogToFile("Invalid feed");
        continue;
    }
    $count = NB_FEED_ITEM_TO_SHOW>0 ? NB_FEED_ITEM_TO_SHOW : sizeof($rssfeed->channel->item);
    for ($i=0 ; $i< $count; $i++) {
        $rssitem = $rssfeed->channel->item[$i];
        $newitem =  array(  'feed' =>  utf8_decode($rssfeed->channel->title) ,
                            'date' =>  strtotime($rssitem->pubDate),
                            'title' => utf8_decode($rssitem->title));
        $RSSItems[]=$newitem;
    }
}

// create an array with all connected display
$display = YDisplay::FirstDisplay();
while ($display) {
	// set the beacon at it max luminosity during
	// sequence recording. Used only for debug purpose
	$module = $display->module();
	$module->set_luminosity(100);
	$module->set_beacon(Y_BEACON_ON);
	// start recording a Sequence
	$display->newSequence();
	// execute command for all rss items
	foreach ($RSSItems as $item) {
		switch ($module->get_productName()) {
			case 'Yocto-MaxiDisplay':
				OutputMaxiDisplay($display,$item);
				break;
			case 'Yocto-Display':
				OutputMaxiDisplay($display,$item);
				break;
			case 'Yocto-MiniDisplay':
				OutputMiniDisplay($display,$item);
				break;
			default:
				LogToFile('Display '.$module->get_productName().' not supported ('.$display->get_hardwareId().')');
				break;
		}
	}
	$display->playSequence('rss');
	$display->saveSequence('rss');
	$display->playSequence('rss');
	// stop the beacon 
	$module->set_luminosity(0);
	$module->set_beacon(Y_BEACON_OFF);
	// look if we get another display connected
	$display = $display->nextDisplay();
}

?>
