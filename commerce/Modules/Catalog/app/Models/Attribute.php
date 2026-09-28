<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    protected $fillable = ['name', 'slug'];

    public function values() { return $this->hasMany(AttributeValue::class); }
}
