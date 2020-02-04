<?php namespace ApiClientTools\App;

class User extends \App\Api\User implements \Illuminate\Contracts\Auth\Authenticatable
{
    public $authIdentifierName = 'id';

    public static function getAuth($id)
    {
        if(empty($id)) {
            return null;
        }

        $auth = new static();
        try {
            $user = static::getAuthData($id);
        } catch (\Exception $e) {
            $user = null;
        }

        if(!$user) {
            return null;
        }

        foreach($user as $param=>$value) {
            $auth->{$param} = $value;
        }
        $auth->identifier = $auth->getIdentifier();

        return $auth;
    }

    public static function getAuthByEmail($email)
    {
        if(empty($email)) {
            return null;
        }

        $auth = new static();
        try {
            $user = static::getAuthDataByEmail($email);
        } catch (\Exception $e) {
            $user = null;
        }

        if(!$user) {
            return null;
        }

        foreach($user as $param=>$value) {
            $auth->{$param} = $value;
        }

        return $auth;
    }

    /**
     * Fetch user by Credentials
     *
     * @param array $credentials
     * @return Illuminate\Contracts\Auth\Authenticatable
     */
    public function fetchUserByCredentials(array $credentials)
    {
        $arr_user = $this->conn->find('users', ['username' => $credentials['username']]);

        if (! is_null($arr_user)) {
            $this->username = $arr_user['username'];
            $this->password = $arr_user['password'];
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \Illuminate\Contracts\Auth\Authenticatable::getAuthIdentifierName()
     */
    public function getAuthIdentifierName()
    {
        return $this->authIdentifierName;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */

    public function retrieveById($identifier)
    {
        return static::getAuth($identifier);
    }

    /**
     * {@inheritDoc}
     * @see \Illuminate\Contracts\Auth\Authenticatable::getAuthIdentifier()
     */
    public function getAuthIdentifier()
    {
        return $this->{$this->getAuthIdentifierName()};
    }

    /**
     * {@inheritDoc}
     * @see \Illuminate\Contracts\Auth\Authenticatable::getAuthPassword()
     */
    public function getAuthPassword()
    {
        return static::getAuthDataPassword($this->{$this->authIdentifierName});
    }

    /**
     * {@inheritDoc}
     * @see \Illuminate\Contracts\Auth\Authenticatable::getRememberToken()
     */
    public function getRememberToken()
    {
        if(isset($this->remember_token)) {
            return $this->remember_token;
        }
        return static::getAuthDataToken($this->{$this->authIdentifierName});
    }

    /**
     * {@inheritDoc}
     * @see \Illuminate\Contracts\Auth\Authenticatable::setRememberToken()
     */
    public function setRememberToken($value)
    {
        return static::setToken($this->{$this->authIdentifierName}, $value);
    }

    /**
     * {@inheritDoc}
     * @see \Illuminate\Contracts\Auth\Authenticatable::getRememberTokenName()
     */
    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    public static function create($payload = [])
    {
        $user = static::createFromPayload($payload);
        if($user) {
            return static::getAuth($user['id']);
        }
    }

    public function update()
    {
        $fillable = [
            'first_name',
            'last_name',
            'details',
        ];

        $payload = [];
        foreach($fillable as $param) {
            if(isset($this->{$param})) {
                $payload[$param] = $this->{$param};
            }
        }
        return static::updateFromPayload($this->{$this->authIdentifierName}, $payload);
    }

    public function save()
    {
        return;
    }

    public function getNameAttribute()
    {
        return $this->getFullNameAttribute();
    }

    public function getFullNameAttribute()
    {
        return trim(trim($this->first_name).' '.trim($this->last_name));
    }

    public function __get($property)
    {
        $property = strtolower($property);
        $method = 'get'.ucfirst(camel_case($property)).'Attribute';

        if(method_exists($this, $method)) {
            return $this->{$method}();
        }
    }

    public function getIdentifierAttribute()
    {
        return $this->getIdentifier();
    }

    public function getIdentifier()
    {
        return $this->{$this->authIdentifierName}.md5($this->{$this->authIdentifierName}.'-'.$this->created_at);
    }

    public static function getByIdentifier(string $identifier)
    {
        if (strlen($identifier) < 33) {
            throw new \Exception('Invalid identifier');
        }

        $id = substr($identifier, 0, -32);
        $user = static::getAuth($id);

        if (!$user) {
            throw new \Exception('Wrong identifier');
        }

        if ($user->identifier == $identifier) {
            return $user;
        } else {
            throw new \Exception('Fake identifier');
        }
    }

}
