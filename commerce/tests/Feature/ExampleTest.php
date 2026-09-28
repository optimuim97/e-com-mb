<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La racine ne renvoie plus une page rendue par Laravel : elle sert la coquille
 * du SPA, que le container du front héberge. Le test la simule, sinon il
 * mesurerait la présence de nginx et non le bon fonctionnement de l'application.
 */
class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        Http::fake([
            '*' => Http::response('<html><head><title>Rosa Beauty</title></head><body></body></html>'),
        ]);

        $this->get('/')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Coquille injoignable : le contrôleur renvoie 503 plutôt qu'une page à
     * moitié construite. nginx rattrape ce code et sert la coquille statique —
     * c'est ce contrat que la règle `error_page` du front suppose vrai.
     */
    public function test_an_unreachable_shell_yields_a_service_unavailable(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->get('/')->assertStatus(503);
    }

    /** Une URL d'API inconnue reste une erreur d'API, jamais une page HTML. */
    public function test_an_unknown_api_route_stays_a_not_found(): void
    {
        $this->getJson('/api/route-qui-nexiste-pas')->assertStatus(404);
    }
}
