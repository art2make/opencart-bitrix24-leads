<?php
class ModelExtensionModuleBitrix24 extends Model
{
	public function getLogs()
	{
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "bitrix24_message_log` ORDER BY `date_sent` DESC LIMIT 10");
		return $query->rows;
	}

	public function deleteAllLogs()
	{
		$this->db->query("DELETE FROM `" . DB_PREFIX . "bitrix24_message_log`");
	}

	public function deleteLogsByDate($date)
	{
		$this->db->query("DELETE FROM `" . DB_PREFIX . "bitrix24_message_log` WHERE `date_sent` <= '" . $this->db->escape($date) . "'");
	}

}