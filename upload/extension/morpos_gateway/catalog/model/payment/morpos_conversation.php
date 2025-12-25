<?php

namespace Opencart\Catalog\Model\Extension\MorposGateway\Payment;

class MorposConversation extends \Opencart\System\Engine\Model
{
    /**
     * Get next attempt sequence for order (0-based -> returns next seq index starting at 1)
     */
    public function getNextAttemptSeq(int $order_id): int
    {
        $query = $this->db->query(
            "SELECT MAX(`attempt_seq`) AS mx FROM `" . DB_PREFIX . "morpos_conversation_attempt` "
            . "WHERE `order_id` = '" . (int)$order_id . "'"
        );

        $mx = isset($query->row['mx']) ? (int)$query->row['mx'] : 0;
        return $mx + 1;
    }

    /**
     * Persist a new attempt and return inserted id or 0 on failure.
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
