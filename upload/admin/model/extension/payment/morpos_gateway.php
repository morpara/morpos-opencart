<?php
class ModelExtensionPaymentMorposGateway extends Model
{
    /**
     * Create the conversation attempts table if it does not exist.
     */
    public function createTable()
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "morpos_conversation_attempt` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` int(11) NOT NULL,
                `attempt_seq` int(11) NOT NULL DEFAULT 0,
                `conversation_id` varchar(64) NOT NULL,
                `data` text DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `order_conversation_unique` (`order_id`,`conversation_id`),
                KEY `order_id_idx` (`order_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;"
        );
    }

    /**
     * Drop the conversation attempts table.
     */
    public function dropTable()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "morpos_conversation_attempt`");
    }

    /**
     * Check if the conversation attempts table exists.
     * 
     * @return bool
     */
    public function tableExists()
    {
        $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "morpos_conversation_attempt'");
        return $query->num_rows > 0;
    }

    /**
     * Ensure the table exists. If not, create it.
     * This is called as a safety check to handle manual installations.
     */
    public function ensureTableExists()
    {
        if (!$this->tableExists()) {
            $this->createTable();
        }
    }
}
