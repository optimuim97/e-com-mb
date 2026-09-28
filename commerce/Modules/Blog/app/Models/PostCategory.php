<?php

namespace Modules\Blog\Models;

use App\Support\Auditing\TracksOperator;
use Illuminate\Database\Eloquent\Model;

class PostCategory extends Model
{
    use TracksOperator;

    protected $fillable = ['name', 'slug', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
