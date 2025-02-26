<?php
/**
 * by Aleksandar Panic
 * Company: 2amigOS!
 *
 **/

namespace dvamigos\Yii2\Notifications\events;

use yii\base\Event;

class UpdateNotification extends EventNotification
{
    /**
     * ID of the notification which will be updated.
     *
     * If value is numeric then that value will be used.
     *
     * If callable then that function will be called to get the ID. ID must be a numeric value.
     *
     * Callable function should be in format:
     * function($eventNotification) {
     *     return 1; // Notification ID
     * }
     *
     * @var callable|int
     */
    public $updateId;

    /**
     * Replaces existing notification using notification manager.
     *
     * @param \yii\base\Event $event
     * @param $type
     * @param $data
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function resolve(Event $event, $type, $data)
    {
        $id = is_callable($this->updateId) ? call_user_func($this->updateId, $this) : $this->updateId;
        $this->getManager()->update($id, $type, $data);
    }
}