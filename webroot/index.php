<?php
/**
*
* get-git-php
* Webhook receiver for git event payloads
* Compatible with gitlab and github
* More about webhooks here: 
* @link https://docs.gitlab.com/ee/user/project/integrations/webhooks.html
* @link https://developer.github.com/webhooks/
*
* @author hrkyoung iam@hrkyoung.com
* @version 0.1.0
* @license MIT License
*/

$debug = false;

if($debug){
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
}
$raw_payload = file_get_contents('php://input');
$payload = json_decode($raw_payload, true);
if( ($_SERVER['REQUEST_METHOD'] !== 'POST') && (!$payload) ){
	header("HTTP/1.0 405 Method Not Allowed"); 
	header("Allow: POST"); 
	exit(1);
}

$metadata = [
	'repo_name' => '',
	'type' => '',
	'event' => '',
	'ip_src' => $_SERVER['REMOTE_ADDR'],
	'domain_src' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
	'secret' => ''
];


if (isset($_SERVER['HTTP_X_GITHUB_EVENT'])){
	$metadata['repo_name'] = $payload['repository']['name'];
	$metadata['type'] = 'github';
	$metadata['event'] = $_SERVER['HTTP_X_GITHUB_EVENT'];
	$metadata['secret'] = isset($_SERVER['HTTP_X_HUB_SIGNATURE']) ? $_SERVER['HTTP_X_HUB_SIGNATURE'] : '';
}else if(isset($_SERVER['HTTP_X_GITLAB_EVENT'])){
	$metadata['repo_name'] = $payload['project']['name'];
	$metadata['type'] = 'gitlab';
	$metadata['event'] = $payload['event_name'];
	$metadata['secret'] = isset($_SERVER['HTTP_X_GITLAB_TOKEN']) ? $_SERVER['HTTP_X_GITLAB_TOKEN'] : '';
}else{
	header("HTTP/1.0 400 Bad Request"); 
	exit(1);
}

#Config
$config_file = __DIR__  . '/../config.json';
if(file_exists($config_file)){
	$config_raw = file_get_contents($config_file);
	preg_match_all("/\"[$]{.*}\"/", $config_raw, $variables_to_subtitute);
	if(count($variables_to_subtitute[0]) != 0){
		foreach($variables_to_subtitute[0] AS $variable){
			$config_raw = str_replace($variable, getArrayVar($payload, $variable), $config_raw);
		}
	}
	$config = json_decode($config_raw, true);
	
}else{
	$config = [
		'get-git-php' => [
			'settings' => [
				'secret' => '12345',
				'ip_whitelist' => [],
				'domain_whitelist' => [],
			],
			'push' => [
				[
					'method' => 'stringIsSame',
					'cmd' => '',
					'param' => [$payload['ref'], 'refs/heads/master'],
					'assert' => true,
					'run' => ["whoami"]
				]
			]
		]

	];
}

if(!$config){
	exit(1);
}

if(!array_key_exists($metadata['repo_name'], $config)
	|| !array_key_exists($metadata['event'], $config[$metadata['repo_name']])
	){
	header("HTTP/1.0 404 Not Found"); 
	exit(1);
}

if( (!verifySecret($metadata['type'], $config[$metadata['repo_name']]['settings']['secret'], $raw_payload, $metadata['secret']))
	|| (!isInList($config[$metadata['repo_name']]['settings']['ip_whitelist'], $metadata['ip_src']))
	|| (!isInList($config[$metadata['repo_name']]['settings']['domain_whitelist'], $metadata['domain_src']))
	){
	header("HTTP/1.0 403 Forbidden"); 
	exit(1);
}

$hooks = $config[$metadata['repo_name']][$metadata['event']];
foreach($hooks AS $index => $hook){
	if($hook['method'] === 'shell'){
		exec($hook['cmd'], $out, $return);
		if($return != $hook['assert']){
			runCMD($hook['run'], $debug);
		}
	}elseif($hook['method'] === 'eval'){
		$eval_statement = "return " . $hook['cmd'] . ";";
		if(eval($eval_statement) == $hook['assert']){
			runCMD($hook['run'], $debug);
		}
	}else{
		$result = call_user_func_array($hook['method'], $hook['param']);
		if($result == $hook['assert']){
			runCMD($hook['run'], $debug);
		}
	}
}

if(!$debug){
	header("HTTP/1.0 204 No Content"); 
}


#########__YOUR_FUNCTIONS_HERE__###########


#########__CORE FUNCTIONS__###########
function verifySecret($type, $secret, $content = null, $hash = null){
	if($secret !== ''){
		if($type === 'gitlab'){
			if($secret === $hash){
				return true;
			}
		}elseif($type === 'github'){
			list($hash_algo, $hash) = explode('=', $hash);
			$my_hash = hash_hmac($hash_algo, $content, $secret);

			if($my_hash === $hash){
				return true;
			}
		}

		return false;
	}

	return true;

}
function isInList($list, $needle){
	if(count($list) != 0){
		return in_array($needle, $list);
	}
	return true;
}

function stringIsSame($string1, $string2){
	if($string1 === $string2){
		return true;
	}
	return false;
}

function runCMD($cmds, $debug){
	foreach($cmds AS $run){
				exec($run, $out, $return);
				if($debug){
					var_dump($out);
					var_dump($return);
				}
				
			}
}

function getArrayVar($payload, $string){
	$string = rtrim(ltrim($string, '"${'), '}"');
	$arr = explode('.', $string);
	if(count($arr) == 0){
		return '';
	}
	$return = $payload;
	for ($ctr = 1; $ctr < count($arr); $ctr++){
		$return = $return[$arr[$ctr]];
	}

	if(is_string($return)){
		$return = '"'. $return . '"';
	}

	return $return;
}
