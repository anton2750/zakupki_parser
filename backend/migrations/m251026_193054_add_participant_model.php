<?php

use yii\db\Migration;

class m251026_193054_add_participant_model extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Create zakupki table
        $this->createTable('{{%participant}}', [
            'id' => $this->primaryKey(),
            'number' => $this->string(255)->null(),
            'inn' => $this->string(255)->null(),
            'ogrn' => $this->string(255)->null(),
            'kpp' => $this->string(255)->null(),
            'jur' => $this->string(255)->null(),
            'type' => $this->string(255)->null(),
            'court' => $this->text()->null(),
            'case_number' => $this->string(255)->null(),
            'date_issue' => $this->string(255)->null(),
            'date_implement' => $this->string(255)->null(),
            'created_at' => $this->string()->notNull(),
            'updated_at' => $this->string()->notNull(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%participant}}');
    }
}
