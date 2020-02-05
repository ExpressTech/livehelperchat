<?php
/**
* 2007-2015 PrestaShop.
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

$cur_dir = getcwd();

ini_set("max_execution_time", "3600");


chdir(getcwd().'/../modules/etlivehelperchat/libraries/');
// echo getcwd();exit;
require_once 'lib/core/lhcore/password.php';
require_once 'lib/core/lhcore/lhsys.php'; // for APC cache.
require_once 'ezcomponents/Base/src/base.php'; // dependent on installation method, see below
require_once 'cli/lib/install.php';

ezcBase::addClassRepository('./', './lib/autoloads');

spl_autoload_register(array('ezcBase','autoload'), true, false);

//erLhcoreClassSystem::init();

// your code here
ezcBaseInit::setCallback(
    'ezcInitDatabaseInstance',
    'erLhcoreClassLazyDatabaseConfiguration'
);

$cfgSite = erConfigClassLhConfig::getInstance();

if ($cfgSite->getSetting('site', 'installed') == true) {
    echo 'Live helper chat already installed'; 

    return 'installed';
}

$instance = erLhcoreClassSystem::instance();

function install_lhc($ini_file)
{
    try {
        $install = new Install($ini_file);

        $response = $install->step1();
        if (is_array($response)) {
            $install->print_errors($response);
        }

        $response = $install->step2();
        if (is_array($response)) {
            $install->print_errors($response);
        }

        $response = $install->step3();
        if (is_array($response)) {
            $install->print_errors($response);
        }

        $response = $install->step4();
        if (is_array($response)) {
            $install->print_errors($response);
        }

        return true;
    } catch (Exception $err) {
        var_dump($err);
        return false;
    }
}

// install_lhc($ini_file);
