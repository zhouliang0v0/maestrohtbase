<?php

namespace Mpociot\BotMan;

use Closure;
use wrapi\slack\slack;
use Opis\Closure\SerializableClosure;
use Mpociot\BotMan\Traits\ProvidesStorage;
use Mpociot\BotMan\Traits\VerifiesServices;
use Mpociot\BotMan\Interfaces\CacheInterface;
use Mpociot\BotMan\Interfaces\DriverInterface;
use Mpociot\BotMan\Interfaces\StorageInterface;
use Mpociot\BotMan\Interfaces\MiddlewareInterface;

/**
 * Class BotMan.
 */
class BotMan
{
    use VerifiesServices, ProvidesStorage;

    /** @var \Symfony\Component\HttpFoundation\ParameterBag */
    public $payload;

    /** @var \Illuminate\Support\Collection */
    protected $event;

    /** @var Message */
    protected $message;

    /** @var string */
    protected $driverName;

    /**
     * Messages to listen to.
     * @var array
     */
    protected $listenTo = [];

    /**
     * The fallback message to use, if no match
     * could be heard.
     * @var callable|null
     */
    protected $fallbackMessage;

    /** @var array */
    protected $matches = [];

    /** @var DriverInterface */
    protected $driver;

    /** @var array */
    protected $config = [];

    /** @var array */
    protected $middleware = [];

    /** @var CacheInterface */
    private $cache;

    /** @var StorageInterface */
    protected $storage;

    /** @var bool */
    protected $loadedConversation = false;

    const DIRECT_MESSAGE = 'direct_message';

    const PUBLIC_CHANNEL = 'public_channel';

    /**
     * BotMan constructor.
     * @param CacheInterface $cache
     * @param DriverInterface $driver
     * @param array $config
     * @param StorageInterface $storage
     */
    public function __construct(CacheInterface $cache, DriverInterface $driver, $config, StorageInterface $storage)
    {
        $this->cache = $cache;
        $this->message = new Message('', '', '');
        $this->driver = $driver;
        $this->config = $config;
        $this->storage = $storage;

        $this->loadActiveConversation();
    }

    /**
     * @param MiddlewareInterface $middleware
     */
    public function middleware(MiddlewareInterface $middleware)
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Set a fallback message to use if no listener matches.
     *
     * @param callable $callback
     */
    public function fallback($callback)
    {
        $this->fallbackMessage = $callback;
    }

    /**
     * @return DriverInterface
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        $messages = $this->getDriver()->getMessages();

        foreach ($this->middleware as $middleware) {
            foreach ($messages as &$message) {
                $middleware->handle($message, $this->getDriver());
            }
        }

        return $messages;
    }

    /**
     * @return Answer
     */
    public function getConversationAnswer()
    {
        return $this->getDriver()->getConversationAnswer($this->message);
    }

    /**
     * @return bool
     */
    public function isBot()
    {
        return $this->getDriver()->isBot();
    }

    /**
     * Get the parameter names for the route.
     *
     * @return array
     */
    protected function compileParameterNames($value)
    {
        preg_match_all('/\{(.*?)\}/', $value, $matches);

        return array_map(function ($m) {
            return trim($m, '?');
        }, $matches[1]);
    }

    /**
     * @param string $pattern the pattern to listen for
     * @param Closure|string $callback the callback to execute. Either a closuer or a Class@method notation
     * @param string $in the channel type to listen to (either direct message or public channel)
     * @return $this
     */
    public function hears($pattern, $callback, $in = null)
    {   
        if (preg_match('||', $pattern)) {
            $patterns = explode('||', $pattern);
            foreach ($patterns as $pattern) {
                $this->addPattern($pattern, $callback, $in);
            }
        } else {
            $this->addPattern($pattern, $callback, $in);
        }

        return $this;
    }

    public function addPattern($pattern, $callback, $in=null) {
        $this->listenTo[] = [
                    'pattern' => $pattern,
                    'callback' => $callback,
                    'in' => $in,
                ];
    }

    public function removePattern($pattern, $callback, $in=null) {
        unset($this->listenTo['pattern'][$pattern]);
        unset($this->listenTo['callback'][$callback]);
    }

    public function removeMemoryPattern($question) {
        $this->removePattern($question, 'ownmemory');
    }

    /**
     * Try to match messages with the ones we should
     * listen to.
     */

    public function listen()
    {
        $heardMessage = false;
        foreach ($this->listenTo as $messageData) {
            $pattern = $messageData['pattern'];
            $callback = $messageData['callback'];


            if ( ($callback != 'ownmemory') && ! $callback instanceof Closure) {
                list($class, $method) = explode('@', $callback);
                $callback = [new $class, $method];
            }
           
            foreach ($this->getMessages() as $message) {

                $talks = $this->driverStorage()->get();
                $key = $message->getMessage();

                if ($talks->has($key)) {
                    $this->message = $message;
                    $this->reply($talks->get($key));
                    return;
                }

                if ($this->isMessageMatching($message, $pattern, $matches) && $this->isChannelValid($message->getChannel(), $messageData['in']) && $this->loadedConversation === false) {
                    if (!$this->driver->isBot()) {

                        $this->message = $message;
                        $heardMessage = true;

                        $parameterNames = $this->compileParameterNames($pattern);
                        $matches = array_slice($matches, 1);

                        if (count($parameterNames) === count($matches)) {
                            $parameters = array_combine($parameterNames, $matches);
                        } else {
                            $parameters = $matches;
                        }
                        $this->matches = $parameters;
                        array_unshift($parameters, $this);
                        call_user_func_array($callback, $parameters);
                    }
                }
            }
        }
        if ($heardMessage === false && ! $this->isBot() && is_callable($this->fallbackMessage) && $this->loadedConversation === false) {
            $this->message = $this->getMessages()[0];
            call_user_func($this->fallbackMessage, $this);
        }
    }

    /**
     * @param Message $message
     * @param string $pattern
     * @param array $matches
     * @return int
     */
    protected function isMessageMatching(Message $message, $pattern, &$matches, $middleware=false)
    {   
        $matches = [];

        $messageText = $message->getMessage();
        $answerText = $this->getConversationAnswer()->getValue();

        $pattern = str_replace('/', '\/', $pattern);
        if ($middleware) {
            $text = '/^'.preg_replace('/\{(\w+?)\}/', '(.*)', $pattern).'$/i';
            $text = preg_replace('@(^\*|\*$)@', '', $text);
        } else {
            $text = $this->getPatternByRule($pattern);
        }

        $regexMatched = (bool) preg_match($text, $messageText, $matches) || (bool) preg_match($text, $answerText, $matches);
        
        // Try middleware first
        foreach ($this->middleware as $middleware) {
            return $middleware->isMessageMatching($message, $pattern, $regexMatched, true);
        }

        return $regexMatched;
    }

     protected function getPatternByRule($pattern) {
        $first = $pattern{0};
        $last = substr($pattern, -1);

        $text = ''.preg_replace('/\{(\w+?)\}/', '(.*)', $pattern).'';
          

        if ($first == '*') {
            $text = substr($text, 1);
            $begin = '@.*';
            $end = '$@';
        }

        if ($last == '*') {
            $text = substr($text, 0, -1);
            $begin = '@^';
            $end = '.*@';
        }

        if (($last == '*') && ($first == '*')) {
            $begin = '/';
            $end = '/';
        }

        if ( ($last != '*') && (($first != '*')) ) {
            $begin = '/^';
            $end = '$/i';
        }

        $text = $begin.$text.$end;
        return $text;   
    }

    /**
     * @param string|Question $message
     * @param string|array $channel
     * @param DriverInterface|null $driver
     * @return $this
     */
    public function say($message, $channel, $driver = null)
    {
        if (is_null($driver)) {
            $drivers = DriverManager::getConfiguredDrivers($this->config);
        } else {
            $drivers = [DriverManager::loadFromName($driver, $this->config)];
        }

        $params = [
            'username' => 'hypertask',
            'icon_url' => 'https://avatars.slack-edge.com/2016-12-29/120882697809_1e723ad86ff82fb50b96_72.png',
            
        ];

        foreach ($drivers as $driver) {
            $matchMessage = new Message('', '', $channel);
            /* @var $driver DriverInterface */
            $driver->reply($message, $matchMessage, $params);
        }

        return $this;
    }

    public function justsay($message, $channel) {

        $data = file_get_contents('/usr/share/htvcenter/plugins/chatbot/thebot/config');
        $config = unserialize($data);
        $client = new slack($config['slack_token']);

        $response = $client->chat->postMessage(array(
            "channel" => $channel,
            "text" => $message,
            "username" => "hypertask",
            'icon_url' => 'https://avatars.slack-edge.com/2016-12-29/120882697809_1e723ad86ff82fb50b96_72.png',
            "as_user" => true,
            "parse" => "full",
            "link_names" => 1,
            "unfurl_links" => true,
            "unfurl_media" => false
          )
        );
    }

    /**
     * @param string|Question $message
     * @param array $additionalParameters
     * @return $this
     */
    public function reply($message, $additionalParameters = [])
    {
        $this->getDriver()->reply($message, $this->message, $additionalParameters);

        return $this;
    }

    public function randomReply(array $messages) {
        $num = array_rand($messages);
        $this->reply($messages[$num]);
    }

    public function getUserId() {
        return $this->message->getUser();
    }

    public function getUserIdByName($name) {
        $data = file_get_contents('/usr/share/htvcenter/plugins/chatbot/thebot/config');
        $config = unserialize($data);
        $client = new slack($config['slack_token']);
        $user = $client->users->list();
        $id = null;
        
        foreach ($user['members'] as $u) {   
            if ($u['name'] == $name) {
                $id = $u['id'];
                break;
            }
        }

        return $id;
    }

    public function getUserName() {
        $data = file_get_contents('/usr/share/htvcenter/plugins/chatbot/thebot/config');
        $config = unserialize($data);
        $client = new slack($config['slack_token']);
        $user = $client->users->info(array("user" => $this->getUserId()));
        return $user['user']['name'];
    }

    /**
     * @param string|Question $message
     * @param array $additionalParameters
     * @return $this
     */
    public function replyPrivate($message, $additionalParameters = [])
    {
        $privateChannel = [
            'channel' => $this->message->getUser(),
        ];

        return $this->reply($message, array_merge($additionalParameters, $privateChannel));
    }

    /**
     * @param Conversation $instance
     */
    public function startConversation(Conversation $instance)
    {
        $instance->setBot($this);
        $instance->run();
    }

    /**
     * @param Conversation $instance
     * @param array|Closure $next
     */
    public function storeConversation(Conversation $instance, $next)
    {
        $this->cache->put($this->message->getConversationIdentifier(), [
            'conversation' => $instance,
            'next' => is_array($next) ? $this->prepareCallbacks($next) : serialize(new SerializableClosure($next, true)),
        ], 30);
    }

    /**
     * Prepare an array of pattern / callbacks before
     * caching them.
     *
     * @param array $callbacks
     * @return array
     */
    protected function prepareCallbacks(array $callbacks)
    {
        foreach ($callbacks as &$callback) {
            $callback['callback'] = serialize(new SerializableClosure($callback['callback'], true));
        }

        return $callbacks;
    }

    /**
     * Look for active conversations and clear the payload
     * if a conversation is found.
     */
    public function loadActiveConversation()
    {
        $this->loadedConversation = false;
        if ($this->isBot() === false) {
            foreach ($this->getMessages() as $message) {
                if ($this->cache->has($message->getConversationIdentifier())) {
                    $convo = $this->cache->pull($message->getConversationIdentifier());
                    $next = false;
                    $parameters = [];
                    if (is_array($convo['next'])) {
                        foreach ($convo['next'] as $callback) {
                            if ($this->isMessageMatching($message, $callback['pattern'], $matches)) {
                                $this->message = $message;
                                $parameters = array_combine($this->compileParameterNames($callback['pattern']), array_slice($matches, 1));
                                $this->matches = $parameters;
                                $next = unserialize($callback['callback']);
                                break;
                            }
                        }
                    } else {
                        $this->message = $message;
                        $next = unserialize($convo['next']);
                    }

                    if (is_callable($next)) {
                        array_unshift($parameters, $this->getConversationAnswer());
                        array_push($parameters, $convo['conversation']);
                        call_user_func_array($next, $parameters);
                        // Mark conversation as loaded to avoid triggering the fallback method
                        $this->loadedConversation = true;
                    }
                }
            }
        }
    }

    /**
     * @param $givenChannel
     * @param $allowedChannel
     * @return bool
     */
    protected function isChannelValid($givenChannel, $allowedChannel)
    {
        /*
         * If the Slack channel starts with a "D" it's a direct message,
         * if it starts with a "C" it is a public channel.
         */
        if ($allowedChannel === self::DIRECT_MESSAGE) {
            return strtolower($givenChannel[0]) === 'd';
        } elseif ($allowedChannel === self::PUBLIC_CHANNEL) {
            return strtolower($givenChannel[0]) === 'c';
        }

        return true;
    }

    /**
     * @return array
     */
    public function getMatches()
    {
        return $this->matches;
    }

    /**
     * @return Message
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Load driver on wakeup.
     */
    public function __wakeup()
    {
        $this->driver = DriverManager::loadFromName($this->driverName, $this->config);
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        $this->driverName = $this->driver->getName();

        return [
            'payload',
            'event',
            'driverName',
            'storage',
            'message',
            'cache',
            'matches',
            'config',
        ];
    }
}
