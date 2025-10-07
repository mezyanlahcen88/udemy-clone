<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Hashids\Hashids; // Required for direct instantiation

/**
 * Trait to automatically handle Hashids encoding/decoding for route model binding
 * and scoping, using the standard 'hashids/hashids' package.
 *
 * This implementation populates the 'hashid' column on creation and uses it
 * for route model binding.
 */
trait WithHashId
{
    /**
     * Boot the trait and register model events.
     * This is used to automatically populate the 'hashid' column upon creation.
     */
    protected static function bootWithHashId()
    {
        static::created(function ($model) {
            // Check if the model has a 'hashid' attribute (column)
            if (array_key_exists('hashid', $model->getAttributes())) {
                // The primary key ($model->id) is now available.
                // Calculate the hashid dynamically.
                $hashid = $model->getHashId();

                // Check if the hashid is not already set and update the database record.
                if (empty($model->hashid)) {
                    // We use $model->update() to persist the change to the database.
                    $model->update(['hashid' => $hashid]);
                }
            }
        });
    }

    /**
     * Gets a configured Hashids instance based on the properties previously defined
     * in getHashIdOptions.
     *
     * @return Hashids
     */
    protected function getHashidsInstance(): Hashids
    {
        // 1. Salt: Use the app key or a dedicated environment variable.
        // If this trait is used outside a Laravel environment where config() is unavailable,
        // you should override this method or define a global constant.
        $salt = config('app.key') ?: env('APP_KEY', 'default-hashids-salt');

        // 2. Minimum Length from previous HashIdOptions
        $minLength = 10;

        // 3. Alphabet from previous HashIdOptions
        $alphabet = 'abcdefghijklmnopqrstuvwxyz1234567890';

        return new Hashids($salt, $minLength, $alphabet);
    }

    /**
     * Decodes the given Hash ID into the primary key (integer ID).
     * @param string $hashid
     * @return int|null
     */
    protected function decodeHashId(string $hashid): ?int
    {
        // Decode the hashid string using the configured Hashids instance.
        $decoded = $this->getHashidsInstance()->decode($hashid);

        // We assume the first element of the array is the primary key.
        return $decoded[0] ?? null;
    }

    /**
     * Returns the encoded Hash ID for the current model instance.
     * This provides a dynamic replacement for the old 'hashid' column access.
     * @return string
     */
    public function getHashId(): string
    {
        // Check if the hashid is already stored in the model's attributes.
        // This makes sure we return the stored value if available,
        // otherwise, we calculate it dynamically using the primary key.
        if (!empty($this->attributes['hashid'])) {
            return $this->attributes['hashid'];
        }

        // Encode the primary key (usually $this->id) using the configured Hashids instance.
        // This is necessary when running inside the 'created' hook.
        return $this->getHashidsInstance()->encode($this->getKey());
    }

    /**
     * Scope a query to find a model by its hash ID.
     * The hash ID is dynamically decoded to find the primary key.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @param string $hashid The hash ID string.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHashid(Builder $builder, string $hashid): Builder
    {
        // When the column is present and populated, it's faster to query directly.
        return $builder->where('hashid', $hashid);

        /*
        // Fallback for dynamic binding if 'hashid' wasn't stored:
        if ($id = $this->decodeHashId($hashid)) {
            // Find the record by its primary key (e.g., 'id')
            return $builder->where($this->getKeyName(), $id);
        }

        // If decoding fails, ensure no records are found.
        return $builder->whereRaw('1 = 0');
        */
    }

    /**
     * Get the primary ID from a hash ID string without retrieving the model.
     *
     * @param string $hashid
     * @return int|null
     */
    public static function getId(string $hashid): ?int
    {
        // Use a new model instance to access the decoding logic.
        return (new static)->decodeHashId($hashid);
    }

    /**
     * Retrieve the model for a bound route parameter.
     * This is crucial for making Route Model Binding work with the dynamic hash ID.
     *
     * @param mixed $value The hash ID string passed in the URL.
     * @param string|null $field The field name for the binding (should be 'hashid').
     * @return \Illuminate\Database\Eloquent\Model|null
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // Only apply custom logic if the route key name matches
        if ($field && $field !== $this->getRouteKeyName()) {
            return parent::resolveRouteBinding($value, $field);
        }

        // Decode the hash ID to the primary key
        if ($id = $this->decodeHashId($value)) {
            // Find the model by its primary key or throw an exception if not found
            return $this->newQuery()->findOrFail($id);
        }

        // If decoding fails, throw a ModelNotFoundException
        throw (new ModelNotFoundException)->setModel(get_class($this));
    }

    /**
     * Get the value of the model's route key (the encoded hash ID) for URL generation.
     *
     * @return string
     */
    public function getRouteKey(): string
    {
        // Encode the primary key (id) for the URL
        return $this->getHashId();
    }

    /**
     * Get the route key name for the model (maintains the original 'hashid' convention).
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'hashid';
    }
}
