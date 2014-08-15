<?php
/*
    PufferPanel - A Minecraft Server Management Panel
    Copyright (c) 2013 Dane Everitt
 
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
 
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with this program.  If not, see http://www.gnu.org/licenses/.
 */
 
/*
 * Initalize Start Time
 */
$pageStartTime = microtime(true);

/*
 * Cloudflare IP Fix
 */
$_SERVER['REMOTE_ADDR'] = (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $_SERVER['REMOTE_ADDR'];

/*
 * Include Dependency Libs
 */
require_once(dirname(dirname(__DIR__)).'/vendor/autoload.php');

/*
 * Report Errors to Bugsnag
 * If you do not want to help us with automatic error
 * reporting please comment out these lines.
 *
 * For security purposes PLEASE COMMENT THIS DEBUGGING CODE
 * OUT BEFORE RUNNING ON A LIVE ENVIRONMENT. This debugging
 * sends data to Bugsnag servers which allow us to track errors
 * that are occuring in the code and see stacktraces and other
 * information. If you are running the panel to test it out
 * and look for bugs you can leave it uncommented as it will
 * help us track bugs and find them quicker. Please comment out
 * the code if you are adding new features to the panel since
 * we do not want to be recieving your bugs.
 */
$bugsnag = new Bugsnag_Client("97e324dd278d7b4b19eab9c6d63c380b");
$bugsnag->setAppVersion('0.7.0-beta');
$bugsnag->setBeforeNotifyFunction('before_bugsnag_notify');

/*
 * Tell Bugsnag to Ingore these and handle exceptions and errors
 */
$bugsnag->setFilters(array('password', 'ssh_user', 'ssh_secret', 'ssh_pub_key', 'ssh_priv_key'));
set_error_handler(array($bugsnag, "errorHandler"));
set_exception_handler(array($bugsnag, "exceptionHandler"));

/*
 * Other Debugging Options
 * To use a local-only debugging option please uncomment the lines
 * below and comment out the bugsnag lines. This debugging can be 
 * used on a live environment if you wish.
 */
//use Tracy\Debugger;
//Debugger::enable(Debugger::PRODUCTION, dirname(__DIR__).'/logs');
//Debugger::$strictMode = TRUE;

/*
 * Has Installer been run?
 */
if(!file_exists(__DIR__.'/configuration.php'))
	throw new Exception("Installer has not yet been run. Please navigate to the installer and run through the steps to use this software.");

/* 
 * Include Required Global Framework Files
 */
require_once('framework.database.connect.php');
require_once('framework.page.php');
require_once('framework.auth.php');
require_once('framework.files.php');
require_once('framework.user.php');
require_once('framework.server.php');
require_once('framework.settings.php');
require_once('framework.ssh2.php');
require_once('framework.log.php');
require_once('framework.query.php');
require_once('framework.language.php');
require_once('framework.functions.php');
require_once('framework.email.php');

/*
 * Initalize Global Framework
 */
$core = new stdClass();
$_l = new stdClass();

/*
 * Initalize Frameworks
 */
$core->settings = new settings();
$core->auth = new \Auth\auth($core->settings);
$core->ssh = new ssh($core->settings->get('use_ssh_keys'));
$core->user = new user($_SERVER['REMOTE_ADDR'], $core->auth->getCookie('pp_auth_token'), $core->auth->getCookie('pp_server_hash'));
$core->server = new server($core->auth->getCookie('pp_server_hash'), $core->user->getData('id'), $core->user->getData('root_admin'));
$core->email = new tplMail($core->settings);
$core->log = new log($core->user->getData('id'));
$core->gsd = new query($core->server->getData('id'));
$core->files = new files();

/*
 * Check Language Settings
 */
if($core->user->getData('language') === false)
	if(!isset($_COOKIE['pp_language']) || empty($_COOKIE['pp_language']))
		$_l = new Language\lang($core->settings->get('default_language'));
	else
		$_l = new Language\lang($_COOKIE['pp_language']);
else
	$_l = new Language\lang($core->user->getData('language'));
	
/*
 * MySQL PDO Connection Engine
 */
$mysql = Database\database::connect();

/*
 * Twig Setup
 */
Twig_Autoloader::register();

$loader = new Twig_Loader_Filesystem(dirname(dirname(__DIR__)).'/app/views/');
$twig = new Twig_Environment($loader, array(
    'cache' => false,
    'debug' => true
));
$twig->addGlobal('lang', $_l->loadTemplates());
$twig->addGlobal('settings', $core->settings->get());
$twig->addGlobal('get', Page\components::twigGET());
if($core->user->getData('root_admin') == 1){ $twig->addGlobal('admin', true); }
?>