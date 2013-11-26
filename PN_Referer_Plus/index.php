<?php
/* 
Referer statistics plugin for Textcube 1.9 (compatible with 1.7, 1.8)
   ----------------------------------
   Version 1.8.5
   Tatter and Friends development team.

   Creator          : inureyes
   Maintainer      : gendoh, inureyes, graphittie
   Modifier         : reznoa, Lael

   Created at       : 2006-08-15
   Last modified at : 2011-12-11

 This plugin shows referer statistics on administration menu.
 For the detail, visit http://forum.tattersite.com/ko


 General Public License
 http://www.gnu.org/licenses/gpl.html

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 
@ This modified-version is maintained by Lael. - (http://lael.be/249)
@ If you have any problem or any suggestion, Please let me know.
*/

function AddingRefererLog_Plus(){
	global $database,$entries;
	$blogid = getBlogId();

	$url_long = POD::escapeString(UTF8::lessenAsEncoding($_SERVER['HTTP_REFERER'], 1024)); //지나치게 긴 리퍼러는 필요없다.

	$pageId = 0;
	$pageTitle = '';
	if (!empty($entries) && (count($entries) == 1)){
		$pageId = $entries[0]['id'];
		$pageTitle = $entries[0]['title'];
	}

	$pageTitle = addslashes($pageTitle);
	$url_long = addslashes($url_long);

	POD::query("INSERT INTO `{$database['prefix']}RefererLogs_Plus` values('{$blogid}', UNIX_TIMESTAMP(), '{$pageId}', '{$pageTitle}', '{$url_long}')");
	POD::query("DELETE FROM `{$database['prefix']}RefererLogs_Plus` WHERE `referred` < UNIX_TIMESTAMP() - 604800");	//delete

	return true;
}

function utf8_urldecode($str, $chr_set='CP949') {
	$callback_function = create_function('$matches, $chr_set="'.$chr_set.'"', 'return iconv("UTF-16BE", $chr_set, pack("n*", hexdec($matches[1])));');
	return rawurldecode(preg_replace_callback('/%u([[:alnum:]]{4})/', $callback_function, $str));
}

function PN_Referer_Default()
{
	global $pluginMenuURL, $pluginSelfParam, $blogURL, $configVal,$database;
	$config = Setting::fetchConfigVal($configVal);

	if(substr(TEXTCUBE_VERSION,0,3)=='1.7'){//Textcube 버전 넘버링은 1.7, 1.8, 1.9 다음에 2.0 이다.
		requireComponent( "Textcube.Model.Statistics");
		requireComponent( "Textcube.Model.Paging");
		requireComponent( "Textcube.Function.misc");
		requireModel('blog.entry');
	}

	if (($_SERVER['REQUEST_METHOD'] == 'POST') && isset($_POST['page']))
		$_GET['page'] = $_POST['page'];

	$page = Setting::getBlogSetting('RowsPerPageReferer',20);
	if (empty($_POST['perPage'])) {
		$perPage = $page;
	} else if ($page != $_POST['perPage']) {
		Setting::setBlogSetting('RowsPerPageReferer',$_POST['perPage']);
		$perPage = $_POST['perPage'];
	} else {
		$perPage = $_POST['perPage'];
	}
?>
						<div id="part-statistics-rank" class="part">
							<h2 class="caption"><span class="main-text"><?php echo _t("리퍼러 순위");?></span></h2>

							<table class="data-inbox" cellspacing="0" cellpadding="0">
								<thead>
									<tr>
										<th class="number"><span class="text"><?php echo _t("순위");?></span></th>
										<th class="site"><span class="text"><?php echo _t("리퍼러");?></span></th>
										<th class="count"><span class="text"><?php echo _t("횟수");?></span></th>
									</tr>
								</thead>
								<tbody>
								<?php
									$temp = Statistics::getRefererStatistics(getBlogId());
									for ($i=0; $i<count($temp); $i++) {
										$record = $temp[$i];

										$className = ($i % 2) == 1 ? 'even-line' : 'odd-line';
										$className .= ($i == sizeof($temp) - 1) ? ' last-line' : '';
								?>
									<tr class="<?php echo $className;?> inactive-class" onmouseover="rolloverClass(this, 'over')" onmouseout="rolloverClass(this, 'out')">
										<td class="rank"><?php echo $i + 1;?>.</td>
										<td class="site"><a href="http://<?php echo Misc::escapeJSInAttribute($record['host']);?>" onclick="window.open(this.href); return false;"><?php echo htmlspecialchars($record['host']);?></a></td><td class="count"><?php echo number_format($record['count']);?></td>
									</tr>
								<?php
									}
								?>
								</tbody>
							</table>
						</div>

						<hr class="hidden" />

						<form id="part-statistics-log" class="part" method="post" action="<?php echo $pluginMenuURL;?>">
							<h2 class="caption"><span class="main-text"><?php echo _t("리퍼러 로그");?></span></h2>

							<table class="data-inbox" cellspacing="0" cellpadding="0" border="0">
								<thead>
									<tr>
										<th width="80" class="number"><span class="text"><?php echo _t("날짜");?></span></th>
										<th class="entryid"><span class="text">#</span></th>
										<th class="site"><span class="text"><?php echo _t("주소");?></span></th>
										<th class="searchterm"><span class="text"><?php echo _t("검색어");?></span></th>
										<th class="searchengine"><span class="text"><?php echo _t("검색엔진");?></span></th>
									</tr>
								</thead>
								<tbody>
<?php
	$more = false;
	list($referers, $paging) = Statistics::getRefererLogsWithPage($_GET['page'], $perPage);

	// Note. purl_arr와 surl_arr는 찾으려는 문자열이 preg_match()의 첫번째 결과로 나오도록 해야한다.
	$purl_arr = array(
		"Naver Blog"			=> "http:\/\/blog.naver.com\/.*[\?&]topReferer\=([^&]*).*"
	);
	$surl_arr = array(
		"Google"				=> "http:\/\/www\.google\..*[\?&]q\=([^&]*).*",
		"Nate"					=> ".*[\.\/]nate\.com.*[\?&]q\=([^&]*).*",
		"Yahoo"					=> ".*[\.\/]search\.yahoo\.com\/.*[\?&]p\=([^&]*).*",
		"Daum"					=> ".*[\.\/]search\.daum\.net.*[\?&]q\=([^&]*).*",
		"Naver"					=> ".*[\.\/](?:search|cafe)\.naver\.com.*[\?&]query\=([^&]*).*",
		"Bing"					=> "http:\/\/www\.bing\.com.*[\?&]q\=([^&]*).*",
		"MetaCrawler"			=> "http:\/\/www\.metacrawler\.com\/search\/.*[\?&]q\=([^&]*).*",
		"Netpia"				=> "http:\/\/find\.netpia\.com\/nlia\/lookup\.phtml?.*[\?&]q\=([^&]*).*",
		"Live Mobile Search"	=> "http:\/\/livemobilesearch\.com\/.*[\?&](?:s|srchtxt)\=([^&]*).*",
		"Cyworld"				=> ".*[\.\/]cyworld\.com\/.*[\?&]search_keyword\=([^&]*).*",
		"Nate"					=> ".*[\.\/]search\.nate\.com\/.*[\?&]q\=([^&]*).*",
		"paran"					=> ".*[\.\/]search\.paran\.com\/search\/.*[\?&]q\=([^&]*).*",
		"Lafent"				=> ".*[\.\/]lafent\.com\/.*\.php.*[\?&](?:query|mkwd)\=([^&]*).*",
		"olleh"					=> ".*[\.\/]olleh.com\/.*[\?&](?:q|Query|)\=([^&]*).*",
	);

	$hide_referer_prefix = ($config['hideReferer']=='1')?"'http://nullrefer.com/?'+":""; //referer_hide_service

	for ($i=0; $i<count($referers); $i++) {

		$record = $referers[$i];

		$record_plus = POD::queryRow($sql="SELECT * FROM `{$database['prefix']}RefererLogs_Plus` WHERE `referred` = '{$record['referred']}' ");
		$record['url'] = $record_plus['url']?$record_plus['url']:$record['url'];
		$record['entryid'] = $record_plus['entryid'];
		$record['entrytitle'] = $record_plus['entrytitle'];

		$className = ($i % 2) == 1 ? 'even-line' : 'odd-line';
		$className .= ($i == sizeof($referers) - 1) ? ' last-line' : '';

		$searchurl = $record['url'];
		$searchengine = '';
		$searchterm = '';


		// 전처리 = 검색주소 찾기
		foreach($purl_arr as $p_engine => $p_pattern) {
			if (preg_match("/$p_pattern/i", $searchurl, $matches)) {
				$searchurl = urldecode($matches[1]);
			}
		}

		// 검색어 찾기
		foreach($surl_arr as $s_engine => $s_pattern) {
			if (preg_match("/$s_pattern/i", $searchurl, $matches)) {
				$searchengine = $s_engine;
				$searchterm = $matches[1];

				// +로 바뀐 띄어쓰기 복구
				$searchterm = str_replace("+", " ", $searchterm);
				// %xx 복호화
				$searchterm = urldecode($searchterm);

				// EUC-KR이면 UTF-8로 변환
				$iconved = false;
				if ($searchterm == iconv("EUC-KR", "EUC-KR", $searchterm)) {
					$searchterm =  iconv("EUC-KR", "UTF-8" , $searchterm);
					$iconved = true;
				}

				// 검색엔진별 예외 처리
				switch ($searchengine)
				{
					case 'Naver':
						// unicode로 된 경우도 있어서 필요
						$searchterm = utf8_urldecode($searchterm);

						// Naver는 특이하게도 iconv()가 잘못 변환할 수 있다.
						// 이 때는 다시 변환 해줘야한다.
						if ( ($iconved === true)
						 &&  ( (strpos($searchurl,'ie=utf8') !== false)
							|| (strpos($searchurl,'where=m') != false) ) )
						{
							$searchterm = iconv("UTF-8", "EUC-KR", $searchterm);
						}
						break;
				}

				$searchterm = htmlspecialchars(trim($searchterm));
			}
		}

		$record['entryid'] = ($record['entryid']=='0')?'':$record['entryid'];
?>
									<tr class="<?php echo $className;?> inactive-class" onmouseover="rolloverClass(this, 'over')" onmouseout="rolloverClass(this, 'out')">
										<td class="date"><?php echo Timestamp::formatDate($record['referred']);?></td>
										<td class="entryid"><?php echo '<a href="'. $blogURL .'/'. $record['entryid'] .'" title="'. htmlspecialchars($record['entrytitle']) .'" onclick="window.open(this.href);return false;">'. $record['entryid'] .'</a>';	?></td>
										<td class="address"><a href="<?php echo Misc::escapeJSInAttribute($record['url']);?>" onclick="window.open(<?php echo $hide_referer_prefix; ?>this.href); return false;" title="<?php echo htmlspecialchars($record['url']);?>"><?php echo fireEvent('ViewRefererURL', htmlspecialchars(UTF8::lessenAsEm($record['url'], 30)), $record);?></a></td>
										<td class="searchterm"><a href="<?php echo Misc::escapeJSInAttribute($searchurl);?>" onclick="window.open(<?php echo $hide_referer_prefix; ?>this.href); return false;" title="Search in <?php echo $searchengine;?>"><?php echo $searchterm?></a></td>
										<td class="searchengine"><?php echo $searchengine?></td>
									</tr>
<?php
	}
?>
								</tbody>
							</table>

							<div class="data-subbox">
								<div id="page-section" class="section">
									<div id="page-navigation">
										<span id="page-list">
<?php
	$paging['prefix'] = $pluginSelfParam . '&page=';
	$pagingTemplate = '[##_paging_rep_##]';
	$pagingItemTemplate = '<a [##_paging_rep_link_##]>[[##_paging_rep_link_num_##]]</a>';
	echo str_repeat("\t", 8).Paging::getPagingView($paging, $pagingTemplate, $pagingItemTemplate).CRLF;
?>
										</span>
									</div>
									<div class="page-count">
										<?php echo Misc::getArrayValue(explode('%1', '한 페이지에 목록 %1건 표시'), 0);?>
										<select name="perPage" onchange="document.getElementById('part-statistics-log').submit()">
<?php
	for ($i = 10; $i <= 30; $i += 5) {
		if ($i == $perPage) {
?>
											<option value="<?php echo $i;?>" selected="selected"><?php echo $i;?></option>
<?php
		} else {
?>
											<option value="<?php echo $i;?>"><?php echo $i;?></option>
<?php
		}
	}
?>
										</select>
										<?php echo Misc::getArrayValue(explode('%1', '한 페이지에 목록 %1건 표시'), 1);?>
									</div>
								</div>
							</div>
						</form>

						<div class="clear"></div>
<?php
}
?>
