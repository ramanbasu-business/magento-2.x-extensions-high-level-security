<?php

namespace Magento\Framework\HTTP\PhpEnvironment {
    class Request
    {
        private $server;

        public function __construct($server)
        {
            $this->server = $server;
        }

        public function getServer($key)
        {
            return isset($this->server[$key]) ? $this->server[$key] : null;
        }
    }
}

namespace Company\Plugin\Helper {
    class Data
    {
        public function cleanString($value)
        {
            return $value;
        }

        public function printModuleVersion()
        {
            return '<version>test</version>';
        }
    }
}

namespace Magento\User\Model\ResourceModel\User {
    class Collection implements \IteratorAggregate
    {
        private $users;
        private $username;

        public function __construct($users)
        {
            $this->users = $users;
        }

        public function addFieldToFilter($field, $value)
        {
            if ($field === 'username') {
                $this->username = $value;
            }

            return $this;
        }

        public function load($printQuery = false)
        {
            return $this;
        }

        public function getSize()
        {
            return count(iterator_to_array($this->getIterator()));
        }

        public function getIterator(): \Traversable
        {
            $matches = array_filter($this->users, function ($user) {
                return $user->getUsername() === $this->username;
            });

            return new \ArrayIterator($matches);
        }
    }
}

namespace Magento\User\Model {
    class UserFactory
    {
        private $users;

        public function __construct($users)
        {
            $this->users = $users;
        }

        public function create()
        {
            return new User($this->users);
        }
    }

    class User
    {
        private $users;
        private $user;

        public function __construct($users)
        {
            $this->users = $users;
        }

        public function load($id)
        {
            $this->user = isset($this->users[$id]) ? $this->users[$id] : null;
            return $this;
        }

        public function getId()
        {
            return $this->user ? $this->user['id'] : null;
        }

        public function verifyIdentity($password)
        {
            return $this->user && $this->user['password'] === $password;
        }
    }
}

namespace Tests {
    class UserRecord
    {
        private $username;
        private $id;

        public function __construct($username, $id)
        {
            $this->username = $username;
            $this->id = $id;
        }

        public function getUsername()
        {
            return $this->username;
        }

        public function getId()
        {
            return $this->id;
        }
    }

    require_once __DIR__ . '/../src/Company/Plugin/Lib/Encoder.php';
    require_once __DIR__ . '/../src/Company/Plugin/Lib/AES.php';

    class EncoderTest
    {
        private $assertions = 0;

        public function run()
        {
            $this->acceptsValidBasicAuthorizationAndNormalizesQueryKeys();
            $this->rejectsQueryStringCredentialsWithoutAuthorizationHeader();
            $this->rejectsMalformedBasicAuthorization();

            echo 'OK (' . $this->assertions . " assertions)\n";
        }

        private function acceptsValidBasicAuthorizationAndNormalizesQueryKeys()
        {
            $server = array(
                'QUERY_STRING' => 'MixedCase=value&another=two',
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('admin:secret'),
            );
            $result = $this->authenticate($server);

            $this->assertSame(1, $result['is_authenticated']);
            $this->assertSame('value', $result['mixedcase']);
            $this->assertSame('two', $result['another']);
        }

        private function rejectsQueryStringCredentialsWithoutAuthorizationHeader()
        {
            $server = array(
                'QUERY_STRING' => 'username=admin&password=secret',
            );
            $result = $this->authenticate($server);

            $this->assertSame('0', $result['is_authenticated']);
            $this->assertSame('admin', $result['username']);
        }

        private function rejectsMalformedBasicAuthorization()
        {
            $server = array(
                'QUERY_STRING' => '',
                'HTTP_AUTHORIZATION' => 'Basic !!!',
            );
            $result = $this->authenticate($server);

            $this->assertSame('0', $result['is_authenticated']);
        }

        private function authenticate($server)
        {
            $users = array(1 => array('id' => 1, 'password' => 'secret'));
            $request = new \Magento\Framework\HTTP\PhpEnvironment\Request($server);
            $helper = new \Company\Plugin\Helper\Data();
            $aes = new \Company\Plugin\Lib\AES();
            $userFactory = new \Magento\User\Model\UserFactory($users);
            $collection = new \Magento\User\Model\ResourceModel\User\Collection(
                array(new UserRecord('admin', 1))
            );

            $encoder = new \Company\Plugin\Lib\Encoder(
                $request,
                $helper,
                $aes,
                $userFactory,
                $collection
            );

            return $encoder->authenticate();
        }

        private function assertSame($expected, $actual)
        {
            $this->assertions++;

            if ($expected !== $actual) {
                throw new \RuntimeException(
                    'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
                );
            }
        }
    }

    try {
        (new EncoderTest())->run();
    } catch (\Exception $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    }
}
