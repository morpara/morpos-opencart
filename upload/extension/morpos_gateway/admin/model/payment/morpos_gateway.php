<?php

namespace Opencart\Admin\Model\Extension\MorposGateway\Payment;

class MorposGateway extends \Opencart\System\Engine\Model
{
    /**
     * Create the conversation attempts table if it does not exist.
     */
    public function createTable(): void
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
    public function dropTable(): void
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "morpos_conversation_attempt`");
    }

    /**
     * Insert a new conversation attempt row.
     * Returns inserted id or 0 on failure.
     */
    public function addAttempt(int $order_id, int $attempt_seq, string $conversation_id, array $data = []): int
    {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "morpos_conversation_attempt` SET "
            . "`order_id` = '" . (int)$order_id . "', "
            . "`attempt_seq` = '" . (int)$attempt_seq . "', "
            . "`conversation_id` = '" . $this->db->escape($conversation_id) . "', "
            . "`data` = '" . $this->db->escape(json_encode($data)) . "'"
        );

        return $this->db->getLastId();
    }

    /**
     * Get attempts for an order, ordered by attempt_seq ascending.
     */
    public function getAttemptsByOrder(int $order_id): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "morpos_conversation_attempt` "
            . "WHERE `order_id` = '" . (int)$order_id . "' "
            . "ORDER BY `attempt_seq` ASC"
        );
        return $query->rows;
    }

    /**
     * Check whether a conversation id exists for order.
     */
    public function conversationExistsForOrder(int $order_id, string $conversation_id): bool
    {
        $query = $this->db->query(
            "SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "morpos_conversation_attempt` "
            . "WHERE `order_id` = '" . (int)$order_id . "' "
            . "AND `conversation_id` = '" . $this->db->escape($conversation_id) . "'"
        );

        return (int)$query->row['c'] > 0;
    }
}
