<?php
class ControllerExtensionModuleBitrix24 extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('extension/module/bitrix24');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('extension/module/bitrix24');

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            $current_password = $this->loadSetting('bitrix24_password');

            if ($this->request->post['bitrix24_password'] !== '') {
                $password = $this->encryptData($this->request->post['bitrix24_password']);
            } else {
                $password = $current_password;
            }

            $data = array(
                'bitrix24_status' => $this->request->post['bitrix24_status'],
                'bitrix24_login' => $this->request->post['bitrix24_login'],
                'bitrix24_password' => $password,
                'bitrix24_domain' => $this->request->post['bitrix24_domain'],
                'bitrix24_order_status' => $this->request->post['bitrix24_order_status'],
                'bitrix24_callback_status' => $this->request->post['bitrix24_callback_status'],
                'bitrix24_order_template' => $this->request->post['bitrix24_order_template'],
                'bitrix24_contacts_status' => $this->request->post['bitrix24_contacts_status'],
                'bitrix24_questions_status' => $this->request->post['bitrix24_questions_status'],
                'bitrix24_unirequest_status' => $this->request->post['bitrix24_unirequest_status'],
                'bitrix24_exclude_customer_group_id' => $this->request->post['bitrix24_exclude_customer_group_id']
            );

            $this->model_setting_setting->editSetting('bitrix24', $data);
            $this->model_setting_setting->editSetting('module_bitrix24', array('module_bitrix24_status' => $this->request->post['bitrix24_status']));

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        $data['action'] = $this->url->link('extension/module/bitrix24', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['user_token'] = $this->session->data['user_token'];
        $data['logs'] = $this->model_extension_module_bitrix24->getLogs();
        $data['clear_action'] = $this->url->link('extension/module/bitrix24/clearLogs', 'user_token=' . $this->session->data['user_token'], true);

        // Загрузка групп покупателей
        $this->load->model('customer/customer_group');
        $data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

        $data['bitrix24_status'] = $this->loadSetting('bitrix24_status');
        $data['bitrix24_login'] = $this->loadSetting('bitrix24_login');
        $data['bitrix24_password'] = $this->decryptData($this->loadSetting('bitrix24_password'));
        $data['bitrix24_domain'] = $this->loadSetting('bitrix24_domain');
        $data['bitrix24_order_status'] = $this->loadSetting('bitrix24_order_status');
        $data['bitrix24_callback_status'] = $this->loadSetting('bitrix24_callback_status');
        $data['bitrix24_contacts_status'] = $this->loadSetting('bitrix24_contacts_status');
        $data['bitrix24_questions_status'] = $this->loadSetting('bitrix24_questions_status');
        $data['bitrix24_unirequest_status'] = $this->loadSetting('bitrix24_unirequest_status');
        $data['bitrix24_exclude_customer_group_id'] = $this->loadSetting('bitrix24_exclude_customer_group_id');
        $data['bitrix24_order_template'] = $this->loadSetting('bitrix24_order_template');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/bitrix24', $data));
    }

    private function loadSetting($key)
    {
        if (isset($this->request->post[$key])) {
            return $this->request->post[$key];
        }
        return $this->config->get($key);
    }

public function clearLogs()
    {
        $this->load->model('extension/module/bitrix24');

        if (isset($this->request->post['log_date']) && !empty($this->request->post['log_date'])) {
            $this->model_extension_module_bitrix24->deleteLogsByDate($this->request->post['log_date']);
        } else {
            $this->model_extension_module_bitrix24->deleteAllLogs();
        }

        $this->session->data['success'] = 'Логи успешно очищены!';
        $this->response->redirect($this->url->link('extension/module/bitrix24', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function install()
    {
        $master_key = bin2hex(random_bytes(32));
        $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` (store_id, code, `key`, `value`) VALUES (0, 'bitrix24', 'bitrix24_master_key', '" . $this->db->escape($master_key) . "')");

        $encryption_key = bin2hex(random_bytes(32));
        $encrypted_key = $this->encryptData($encryption_key);
        $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` (store_id, code, `key`, `value`) VALUES (0, 'bitrix24', 'bitrix24_encryption_key', '" . $this->db->escape($encrypted_key) . "')");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "bitrix24_message_log` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `message_type` VARCHAR(255) NOT NULL,
        `message_data` TEXT NOT NULL,
        `date_sent` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
    }

    private function encryptData($data)
    {
        $key = $this->getMasterKey();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    private function decryptData($encrypted_data)
    {
        $key = $this->getMasterKey();
        $data = base64_decode($encrypted_data);
        $iv_length = 16;
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }

    private function getMasterKey()
    {
        return $this->config->get('bitrix24_master_key');
    }

    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "bitrix24_message_log`");
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/bitrix24')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
}