<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vérifie que les messages de validation sont traduits et que la langue
 * suit la préférence du client : en-tête `X-Locale` (envoyé par le SPA)
 * en priorité, puis négociation `Accept-Language`, sinon français.
 *
 * NB : le client de test Symfony injecte un `Accept-Language: en` par défaut ;
 * on envoie donc explicitement la préférence, comme le fait le vrai front.
 */
class LocaleValidationTest extends TestCase
{
    use RefreshDatabase;

    /*
     * Ces deux cas envoyaient auparavant une adresse mal formée (« invalide »)
     * pour déclencher la règle `email`. Le champ accepte désormais aussi un
     * identifiant court (« souattara »), pour les comptes sans adresse : sa
     * règle de format a donc disparu, et « invalide » est une saisie
     * parfaitement légitime. On déclenche la même vérification — les messages
     * sont-ils traduits ? — sur la règle qui subsiste : `required`.
     */

    public function test_validation_errors_are_translated_in_french(): void
    {
        // Le SPA envoie X-Locale=fr (langue par défaut de l'interface).
        $res = $this->withHeader('X-Locale', 'fr')
            ->postJson('/api/auth/login', []);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['email', 'password']);

        $errors = $res->json('errors');
        // Plus jamais de clé brute type "validation.required"
        $this->assertStringNotContainsString('validation.', $errors['password'][0]);
        $this->assertSame('Le champ mot de passe est obligatoire.', $errors['password'][0]);
        $this->assertStringContainsString('adresse e-mail', $errors['email'][0]);
    }

    public function test_x_locale_header_switches_to_english(): void
    {
        $res = $this->withHeader('X-Locale', 'en')
            ->postJson('/api/auth/login', []);

        $res->assertUnprocessable();
        $this->assertSame('The password field is required.', $res->json('errors.password.0'));
        $this->assertStringContainsString('required', $res->json('errors.email.0'));
    }

    /**
     * Le corollaire de ce qui précède : un identifiant court passe la
     * validation. S'il était de nouveau refusé par un `email` réintroduit,
     * aucun livreur ne pourrait se connecter.
     */
    public function test_un_identifiant_court_passe_la_validation_du_login(): void
    {
        $res = $this->postJson('/api/auth/login', [
            'email'    => 'souattara',
            'password' => 'motdepasse',
        ]);

        // Rejeté parce que le compte n'existe pas, non parce que la saisie
        // n'aurait pas la forme d'une adresse.
        $res->assertUnprocessable();
        $this->assertSame('Identifiants incorrects.', $res->json('errors.email.0'));
    }

    public function test_x_locale_takes_priority_over_accept_language(): void
    {
        // Navigateur anglais mais interface en français → le français gagne.
        $res = $this->withHeaders(['X-Locale' => 'fr', 'Accept-Language' => 'en-US,en;q=0.9'])
            ->postJson('/api/auth/login', []);

        $res->assertUnprocessable();
        $this->assertSame('Le champ adresse e-mail est obligatoire.', $res->json('errors.email.0'));
    }

    public function test_accept_language_is_used_as_fallback_without_x_locale(): void
    {
        // Sans X-Locale : négociation Accept-Language (français).
        $res = $this->withHeaders(['X-Locale' => '', 'Accept-Language' => 'fr-FR,fr;q=0.9'])
            ->postJson('/api/auth/login', []);

        $res->assertUnprocessable();
        $this->assertSame('Le champ adresse e-mail est obligatoire.', $res->json('errors.email.0'));
    }
}
