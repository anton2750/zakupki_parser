<?php

use yii\db\Migration;

class m251026_214923_add_indexes_participant_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createIndex('idx_participant_number', '{{%participant}}', 'number');
        $this->createIndex('idx_participant_inn', '{{%participant}}', 'inn');
        $this->createIndex('idx_participant_ogrn', '{{%participant}}', 'ogrn');
        $this->createIndex('idx_participant_kpp', '{{%participant}}', 'kpp');
        $this->createIndex('idx_participant_jur', '{{%participant}}', 'jur');
        $this->createIndex('idx_participant_type', '{{%participant}}', 'type');
        $this->createIndex('idx_participant_case_number', '{{%participant}}', 'case_number');
        $this->createIndex('idx_participant_date_issue', '{{%participant}}', 'date_issue');
        $this->createIndex('idx_participant_date_implement', '{{%participant}}', 'date_implement');
        $this->createIndex('idx_participant_created_at', '{{%participant}}', 'created_at');
        $this->createIndex('idx_participant_updated_at', '{{%participant}}', 'updated_at');
        
        // Составные индексы
        $this->createIndex('idx_participant_inn_ogrn', '{{%participant}}', ['inn', 'ogrn']);
        $this->createIndex('idx_participant_type_created', '{{%participant}}', ['type', 'created_at']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx_participant_type_created', '{{%participant}}');
        $this->dropIndex('idx_participant_inn_ogrn', '{{%participant}}');
        
        $this->dropIndex('idx_participant_updated_at', '{{%participant}}');
        $this->dropIndex('idx_participant_created_at', '{{%participant}}');
        $this->dropIndex('idx_participant_date_implement', '{{%participant}}');
        $this->dropIndex('idx_participant_date_issue', '{{%participant}}');
        $this->dropIndex('idx_participant_case_number', '{{%participant}}');
        $this->dropIndex('idx_participant_type', '{{%participant}}');
        $this->dropIndex('idx_participant_jur', '{{%participant}}');
        $this->dropIndex('idx_participant_kpp', '{{%participant}}');
        $this->dropIndex('idx_participant_ogrn', '{{%participant}}');
        $this->dropIndex('idx_participant_inn', '{{%participant}}');
        $this->dropIndex('idx_participant_number', '{{%participant}}');
    }

}
