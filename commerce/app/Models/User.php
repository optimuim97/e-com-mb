<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Order\Models\Order;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, Billable;

    protected $fillable = ['name', 'email', 'username', 'password', 'phone', 'order_pin', 'facebook_id', 'google_id', 'avatar_url', 'auth_provider'];

    protected $hidden = ['password', 'remember_token', 'order_pin'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }


    /**
     * Fabrique un identifiant court à partir du nom : « Sidik Ouattara »
     * donne « souattara » — initiale du prénom, puis le nom de famille.
     *
     * Il est dit à voix haute et saisi sur un clavier de téléphone, souvent
     * debout. D'où les accents retirés (« Traéoré » et « Traore » doivent
     * mener au même compte) et les espaces, tirets et apostrophes écartés :
     * personne ne les reproduit à l'identique.
     *
     * Un homonyme reçoit un suffixe numérique plutôt qu'un refus : c'est à
     * l'appelant de prévenir, pas au système de bloquer la création.
     */
    public static function generateUsername(string $nom, ?int $ignorerId = null): string
    {
        $base = static::usernameBase($nom);

        $candidat = $base;
        $suffixe  = 1;

        while (static::query()
            ->where('username', $candidat)
            ->when($ignorerId, fn ($q) => $q->whereKeyNot($ignorerId))
            ->exists()
        ) {
            $candidat = $base . (++$suffixe);
        }

        return $candidat;
    }

    /** « Sidik Ouattara » -> « souattara ». Sans unicité : voir generateUsername(). */
    public static function usernameBase(string $nom): string
    {
        $propre = preg_replace('/[^a-z ]/', '', strtolower(
            // Translitération : « Koné » -> « Kone ».
            iconv('UTF-8', 'ASCII//TRANSLIT', $nom) ?: $nom
        ));

        $mots = array_values(array_filter(explode(' ', trim((string) $propre))));

        if ($mots === []) {
            return 'user';
        }

        // Un seul mot : il fait l'identifiant à lui seul.
        if (count($mots) === 1) {
            return substr($mots[0], 0, 30);
        }

        // Initiale du premier mot + dernier mot. Les prénoms composés et les
        // seconds prénoms sont ignorés : c'est le nom de famille qui distingue.
        return substr($mots[0], 0, 1) . substr(end($mots), 0, 29);
    }

    public function addresses() { return $this->hasMany(Address::class); }
    public function orders()    { return $this->hasMany(Order::class); }
    public function cart()      { return $this->hasOne(Cart::class); }
}
