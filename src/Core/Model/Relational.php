<?php

namespace Core\Model;

trait Relational
{
    public function hasOne(string $model, string $foreign_key, string $local_key): Model
    {
        return (new $model)->where($foreign_key, $this->{$local_key})->first();
    }

    public function belongsTo(string $model, string $foreign_key, string $local_key)
    {
    }

    public function hasMany(string $model, string $foreign_key, string $local_key): Model
    {
        // $data = [];

        // foreach ($this->attribute() as $value) {
        //     $data[] = (new $model)->where($foreign_key, $value->{$local_key})->first();
        // }

        // return $data;

        return (new $model)->where($foreign_key, $this->{$local_key})->get();
    }
}
