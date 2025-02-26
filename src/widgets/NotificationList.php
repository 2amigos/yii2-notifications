<?php

namespace dvamigos\Yii2\Notifications\widgets;

use dvamigos\Yii2\Notifications\NotificationInterface;
use dvamigos\Yii2\Notifications\NotificationManager;
use Yii;
use yii\di\Instance;
use yii\helpers\ArrayHelper;

/**
 * by Aleksandar Panic
 * Company: 2amigOS!
 *
 **/
class NotificationList extends \yii\base\Widget
{
    /**
     * @var string|NotificationManager
     */
    public $manager = 'notification';

    /**
     * User ID for which notifications should be shown.
     *
     * @var null|int
     */
    public $userId = null;

    /**
     * Container template for listing notifications.
     *
     * Available:
     * {notifications} - Lists all notifications in that place.
     * {emptyText} - Data which will be rendered if there are no notifications.
     *               If there are notification then this is replaced with empty string.
     * {totalCount} - Returns total count of notifications
     * {readCount} - Returns count of read notifications
     * {unreadCount} - Returns count of read notifications
     * {section.key} - renders a section from $sections list. Where key is section name.
     *
     * If this is callable then this function will be called and it must return a string result.
     * This result will not be processed for template strings.
     *
     * Callback is in format:
     * function($notifications, NotificationList $widget) {
     *     return "Result.";
     * }
     *
     * @var string|callable
     */
    public $containerTemplate = "{notifications}{emptyText}";

    /**
     * Item template
     *
     * Allowed string:
     * {text} - only available when right side of notification type in manager is string
     * {text.key} - only available when right side of the notification type in manager is associative array.
     *              key represents the key of that array.
     * {timestamp} - formatted timestamp
     * {section.key} - renders a section from $sections list. Where key is section name.
     * {notification.key} - renders a value from notification object directly. Key represents parameter from notification object.
     *
     * If this is callable then this function will be called and it must return a string result.
     * This result will not be processed for template strings.
     *
     * Callback is in format:
     * function(NotificationInterface $notification, NotificationList $widget) {
     *     return "Result.";
     * }
     *
     * @see NotificationWidget::$sections
     * @var string|callable
     */
    public $itemTemplate = '{notification.type} at {timestamp}';

    /**
     * Rendering sections
     *
     * Should be in format:
     * [
     *     'key' => function(NotificationItem $item) {
     *          return 'result';
     *     }
     * ]
     *
     * @var array
     */
    public $sections = [];

    /**
     * Timestamp format in Formatter format.
     *
     * @var string
     */
    public $timestampFormat = 'php:m/d/Y H:i:s';


    /**
     * Glue for joining notifications when rendering
     *
     * @var string
     */
    public $listGlue = PHP_EOL;

    /**
     * Text which will be rendered if there are no notifications present.
     *
     * @var string
     */
    public $emptyText = 'No notifications available.';


    protected $itemTemplateReplacements = null;

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();

        $this->manager = Instance::ensure($this->manager, NotificationManager::class);
    }


    public function run()
    {
        return $this->renderNotifications($this->getNotifications());
    }

    /**
     * Renders notification text based on template.
     *
     * @param NotificationInterface $notification
     * @return string
     */
    public function renderNotification(NotificationInterface $notification)
    {
        if (is_callable($this->itemTemplate)) {
            return call_user_func_array($this->itemTemplate, [$notification, $this]);
        }

        return $this->renderNotificationTemplate($notification);
    }

    /**
     * Returns context for rendering information about notification.
     *
     * @param NotificationInterface $notification
     * @return array
     */
    protected function getNotificationContext(NotificationInterface $notification)
    {
        return [
            'text' => $notification->getCompiledText(),
            'notification' => $notification
        ];
    }

    /**
     * Compile template replacements for use when rendering single notification
     * @param $template
     * @return array
     */
    protected function compileTemplateReplacements($template)
    {
        preg_match_all("/\{([^\}]+)\}/", $template, $matches);

        $context = [
            'section' => $this->sections
        ];

        $replacements = [];
        foreach ($matches[1] as $key) {
            $section = ArrayHelper::getValue($context, $key);

            if ($section !== null) {
                $replacements["{{$key}}"] = $section;
            } else {
                $replacements["{{$key}}"] = $key;
            }
        }

        return $replacements;
    }

    /**
     * Returns notifications
     * @return NotificationInterface[]
     * @throws \yii\base\InvalidConfigException
     */
    protected function getNotifications()
    {
        return $this->manager->getNotifications($this->userId);
    }

    /**
     * Renders notifications
     *
     * @param $notifications
     * @return mixed|string
     */
    protected function renderNotifications($notifications)
    {
        if (!is_callable($this->itemTemplate)) {
            $this->itemTemplateReplacements = $this->compileTemplateReplacements($this->itemTemplate);
        } else {
            $this->itemTemplateReplacements = [];
        }

        if (is_callable($this->containerTemplate)) {
            return call_user_func_array($this->containerTemplate, [$notifications, $this]);
        }

        return $this->renderContainerTemplate($notifications);
    }

    protected function renderTemplate($context, $template, $templateReplacements, $stringReplacements = [])
    {
        $replacements = [];
        foreach ($templateReplacements as $key => $item) {
            if (is_string($item)) {
                $replacements[$key] = ArrayHelper::getValue($context, $item);
            } else if (is_callable($item)) {
                $replacements[$key] = $item($context, $this);
            }
        }

        foreach ($stringReplacements as $key => $item) {
            $replacements[$key] = $item;
        }

        return strtr($template, $replacements);
    }

    protected function renderContainerTemplate($notifications): string
    {
        $templateReplacements = $this->compileTemplateReplacements($this->containerTemplate);

        $totalCount = count($notifications);
        $readCount = count(array_filter($notifications, function (NotificationInterface $n) {
            return $n->isRead();
        }));
        $unreadCount = $totalCount - $readCount;

        $stringReplacements = [
            '{notifications}' => implode($this->listGlue, array_map(function (NotificationInterface $n) {
                return $this->renderNotification($n);
            }, $notifications)),
            '{emptyText}' => $totalCount == 0 ? $this->emptyText : '',
            '{totalCount}' => $totalCount,
            '{readCount}' => $readCount,
            '{unreadCount}' => $unreadCount,
        ];

        return $this->renderTemplate([], $this->containerTemplate, $templateReplacements, $stringReplacements);
    }

    protected function renderNotificationTemplate(NotificationInterface $notification): string
    {
        $context = $this->getNotificationContext($notification);

        $stringReplacements = [
            '{timestamp}' => Yii::$app->formatter->asDate($notification->getTimestamp(), $this->timestampFormat)
        ];

        return $this->renderTemplate(
            $context,
            $this->itemTemplate,
            $this->itemTemplateReplacements,
            $stringReplacements
        );
    }
}