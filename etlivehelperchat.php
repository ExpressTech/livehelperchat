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

if (!defined('_PS_VERSION_')) {
    exit;
}

class Etlivehelperchat extends Module
{
    public function __construct()
    {
        $this->name = 'etlivehelperchat';
        $this->tab = 'front_office_features';
        $this->version = '1.2.3';
        $this->author = 'Express Tech';
        $this->need_instance = 0;
        $this->is_configurable = 1;
        $this->bootstrap = 1;
        $this->module_key = '8131187eb2d3fdab4d233da310bd34a9';
        $this->tabClassName = 'LiveHelper';
        $this->displayMenuName = $this->l('Live Helper Chat');
        parent::__construct();
        $this->displayName = $this->l('Live Helper Chat');
        $this->description = $this->l('Flexible and standalone live support chat for your shop');

        $this->confirmUninstall = $this->l('This will delete all chat history and settings?');
    }

    public function install()
    {

        // $id_tab = Tab::getInstanceFromClassName($this->tabClassName);

        //add new tab if already not there.
        // if ($id_tab->name == "") {

        //     $tab = new Tab();
        //     $tab->class_name = $this->tabClassName;
        //     $tab->id_parent = 18;
        //     $tab->module = $this->name;
        //     $tab->active = 1;
        //     $languages = Language::getLanguages();
        //     foreach ($languages as $language)
        //       $tab->name[$language['id_lang']] = $this->displayMenuName;
        //     $tab->add();
        // }

        if (_DB_PREFIX_ == 'lh_') {
            //THROW ERROR
            return false;
        }
        if (!$this->_install() || !parent::install() || !$this->registerHook('footer') || !$this->registerHook('displayBackOfficeHeader') || !$this->registerHook('actionObjectEmployeeAddAfter')) {
            //removed - || !$this->registerHook('displayBackOfficeFooter')
            return false;
        }

        //copy Admin theme
        // copy(dirname(__FILE__).'/views/css/bootstrap.min.css');
        // Configuration::updateValue('LHC_EMBED_CODE', '');
        // Configuration::updateValue('LHC_BACK_WIDGET', 1);
        Configuration::updateValue('LHC_IS_INSTALLED', false);

        return true;
    }

    public function uninstall()
    {
        // $id_tab = Tab::getIdFromClassName($this->tabClassName);

            // if ($id_tab) {
            //   $tab = new Tab($id_tab);
            //   $tab->delete();
            // }

            // Configuration::deleteByName('LHC_EMBED_CODE');
            // Configuration::deleteByName('LHC_BACK_WIDGET');
            Configuration::deleteByName('LHC_IS_INSTALLED');

        $this->_removeLHC();

        return parent::uninstall();
    }

    private function _removeLHC()
    {
        while (true) {
            //recursively drop all lh_* tables.
            //since group_concat has 1024 chars limitations.
            $lh_tables_to_drop = Db::getInstance()->executeS("
                SELECT GROUP_CONCAT( 'DROP TABLE IF EXISTS ',table_name , ';' SEPARATOR '') 
                AS statement FROM information_schema.tables 
                WHERE table_schema = '"._DB_NAME_."' and table_name LIKE 'lh_%';
            ");
            $lh_tables_to_drop = $lh_tables_to_drop[0]['statement'];
            //dropping tables
            
            // echo $lh_tables_to_drop;
            if ($lh_tables_to_drop == '') {
                break;
            }
            Db::getInstance()->execute($lh_tables_to_drop);
            // echo Db::getInstance()->getMsgError();exit;
        }

        //Let LHC know that we it has been uninstalled
        $settings = Tools::file_get_contents(dirname(__FILE__).'/libraries/settings/settings.ini.php');

        $settings = str_replace("'installed' => true,", "'installed' => false,", $settings);

        file_put_contents(dirname(__FILE__).'/libraries/settings/settings.ini.php', $settings);
    }

    private function _addNewLHCUser($username, $password, $email, $name, $surname, $role = 'Operators')
    {
        if ($role == 'Administrators') {
            $group_id = 1;
        } else {
            $group_id = 2;
        }
        Db::getInstance()->executeS(
            "INSERT INTO `lh_users` (`username`, `password`, `email`, `name`, `surname`, `all_departments`) VALUES('$username', '$password', '$email', '$name', '$surname', 1)"
        );
        //get the just added user id
        $user_id = Db::getInstance()->Insert_ID();

        //put the user into a role
        Db::getInstance()->executeS(
            "INSERT INTO `lh_groupuser` (`group_id`, `user_id`) VALUES('$group_id', '$user_id')"
        );
        echo Db::getInstance()->getMsgError();
    }

    private function _removeCacheFiles() {

    }

    private function _install()
    {   

        



        $cur_dir = '';
        $ini_file = dirname(__FILE__).'/prestashop.settings.ini';
        require_once dirname(__FILE__).'/_install.php';

        //clear cache files
        $CacheManager = erConfigClassLhCacheConfig::getInstance();
        $CacheManager->expireCache();

        // var_dump($this->context->employee);exit;

        //prefill the ini file with details for installation.
        define('__ETLHC_ADMINUSER__', $this->context->employee->email);
        define('__ETLHC_ADMINEMAIL__', $this->context->employee->email);
        define('__ETLHC_ADMINNAME__', $this->context->employee->firstname);
        define('__ETLHC_ADMINSURNAME__', $this->context->employee->lastname);
        define('__ETLHC_ADMINPASS__', $this->context->employee->passwd);

        //invoke the install function.
        $lhc_resp = install_lhc($ini_file);

        if($lhc_resp === 'installed') {
            exit;
            return true;
        }

        if ($lhc_resp !== true) {
            //installation failed. try to remove stale installation
            $this->_removeLHC();

            // return false;

            //now try again
            $lhc_resp = install_lhc($ini_file);
        }

        if ($lhc_resp === true) {
            //change LHC configuraton and other activities here.

            //put secret hash and enable auto login.
            $autologin_hash = $this->context->employee->passwd;
            Configuration::updateValue('LHC_AUTOLOGIN_HASH', $autologin_hash);

            $lh_tables_to_drop = Db::getInstance()->executeS(
                "UPDATE lh_chat_config SET value='a:3:{i:0;b:0;s:11:\"secret_hash\";s:32:\"$autologin_hash\";s:7:\"enabled\";i:1;}' WHERE identifier = 'autologin_data'"
            );

            //add all current employees
            $emps = Employee::getEmployees(true);
            foreach ($emps as $emp) {
                $emp = new Employee((int) $emp['id_employee']);

                //check if the employee can be assigned as Admiin or Operator
                $role = 'Operators';
                if ($emp->id_profile == 1) {
                    $role = 'Administrators';
                }
                //skip current user. of course.
                if ($emp->email !== $this->context->employee->email) {
                    $this->_addNewLHCUser($emp->email, $emp->passwd, $emp->email, $emp->firstname, $emp->lastname, $role);
                }
            }
        }

        //back to orig directory.
        chdir($cur_dir);

        return $lhc_resp;
    }

    public function hookActionObjectEmployeeAddAfter($params)
    {
        // var_dump($params);
        $emp = $params['object'];
        //check if the employee can be assigned as Admiin or Operator
        $role = 'Operators';
        if ($emp->id_profile == 1) {
            $role = 'Administrators';
        }
        $this->_addNewLHCUser($emp->email, $emp->passwd, $emp->email, $emp->firstname, $emp->lastname, $role);
    }

    public function hookFooter($params)
    {
        if (Configuration::get('LHC_EMBED_CODE')) {
            $code = '<script type="text/javascript">'.Configuration::get('LHC_EMBED_CODE').'</script>';
        }
        $baseURL = $this->context->shop->getBaseURL(true);

        $this->context->smarty->assign('baseURL', $baseURL);

        $code = $this->context->smarty->fetch($this->local_path.'views/templates/front/widget.tpl');

        // echo $code;exit;

        //autodetect language and change in front office
        $lang_code = $this->context->language->iso_code;
        if ($lang_code !== 'en') {
            $lhc_settings = include_once 'libraries/settings/settings.ini.php';
            //normalize the useful part
            $lang = array();
            foreach ($lhc_settings['settings']['site_access_options'] as $key => $ln) {
                $lang[$key] = $ln['content_language'];
            }

            if ($lhc_lang = array_search($lang_code, $lang)) {
                //return 'hi';
                //return $lhc_lang;
                $code = str_replace('index.php', 'index.php/'.$lhc_lang, $code);
            }
        }

        return $code;
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') === $this->name) {
            $this->context->controller->addCSS($this->_path.'views/css/lhc.css', 'all');
        }
    }

    public function generateAutoLoginLink($params)
    {
        $dataRequest = array();
        $dataRequestAppend = array();

        // Destination ID
        if (isset($params['r'])) {
            $dataRequest['r'] = $params['r'];
            $dataRequestAppend[] = '/(r)/'.rawurlencode($params['r.encoded']);
        }

        // User ID
        if (isset($params['u']) && is_numeric($params['u'])) {
            $dataRequest['u'] = $params['u'];
            $dataRequestAppend[] = '/(u)/'.rawurlencode($params['u']);
        }

        // Username
        if (isset($params['l'])) {
            $dataRequest['l'] = $params['l'];
            $dataRequestAppend[] = '/(l)/'.rawurlencode($params['l']);
        }

        if (!isset($params['l']) && !isset($params['u'])) {
            throw new Exception('Username or User ID has to be provided');
        }

        // Expire time for link
        if (isset($params['t'])) {
            $dataRequest['t'] = $params['t'];
            $dataRequestAppend[] = '/(t)/'.rawurlencode($params['t']);
        }

        $hashValidation = sha1($params['secret_hash'].sha1($params['secret_hash'].implode(',', $dataRequest)));

        return "index.php/user/autologin/{$hashValidation}".implode('', $dataRequestAppend);
    }

    public function getContent()
    {
        $shopbase = $this->context->shop->getBaseURL(true);
        // echo $shopbase;exit;
        $chatadmin = $shopbase.'../modules/etlivehelperchat/libraries/';

        // require_once('../modules/etlivehelperchat/libraries/doc/autologin.php');

        //autologin link
        $secret_hash = Configuration::get('LHC_AUTOLOGIN_HASH');

        // echo $secret_hash;
        //since Prestashop Addons forbid base64_encode, we have directly used encoded value here.
        $chatadmin .= $this->generateAutoLoginLink(array('r' => 'user', 'r.encoded' => 'dXNlcg==', 'l' => $this->context->employee->email,  't' => time() + 60, 'secret_hash' => $secret_hash));

        $this->context->smarty->assign('chatadmin', $chatadmin);

        return $this->context->smarty->fetch($this->local_path.'views/templates/admin/settings.tpl');
    }
}
