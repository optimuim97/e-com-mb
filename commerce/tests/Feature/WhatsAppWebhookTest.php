<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Le webhook WhatsApp est une URL publique : Meta l'appelle sans jeton
 * d'authentification. Sa seule protection est la signature HMAC des POST
 * et la comparaison du token de vérification sur le GET — d'où ces tests.
 */
class WhatsAppWebhookTest extends TestCase
{
    private const SECRET = 'secret-app-meta-pour-les-tests';
    private const VERIFY = 'token-de-verification-pour-les-tests';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.verify_token' => self::VERIFY,
            'services.whatsapp.app_secret'   => self::SECRET,
        ]);
    }

    public function test_verification_renvoie_le_challenge_en_texte_brut(): void
    {
        $res = $this->get('/api/whatsapp/webhook?' . http_build_query([
            'hub_mode'         => 'subscribe',
            'hub_verify_token' => self::VERIFY,
            'hub_challenge'    => '1234567890',
        ]));

        $res->assertOk();
        // Meta compare la réponse au challenge caractère pour caractère :
        // le moindre habillage JSON ferait échouer l'enregistrement.
        $this->assertSame('1234567890', $res->getContent());
    }

    public function test_verification_refusee_si_le_token_ne_correspond_pas(): void
    {
        $this->get('/api/whatsapp/webhook?' . http_build_query([
            'hub_mode'         => 'subscribe',
            'hub_verify_token' => 'mauvais-token',
            'hub_challenge'    => '1234567890',
        ]))->assertForbidden();
    }

    public function test_evenement_signe_est_accepte(): void
    {
        $payload = ['entry' => [[
            'changes' => [[
                'value' => ['messages' => [[
                    'from' => '2250707849883',
                    'type' => 'text',
                    'id'   => 'wamid.test',
                    'text' => ['body' => 'Bonjour'],
                ]]],
            ]],
        ]]];

        $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', json_encode($payload), self::SECRET),
        ])->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_evenement_non_signe_est_rejete(): void
    {
        $this->postJson('/api/whatsapp/webhook', ['entry' => []])
            ->assertForbidden();
    }

    public function test_evenement_mal_signe_est_rejete(): void
    {
        $this->postJson('/api/whatsapp/webhook', ['entry' => []], [
            'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', 'un autre corps', self::SECRET),
        ])->assertForbidden();
    }

    public function test_charge_utile_inattendue_ne_fait_pas_echouer_la_reponse(): void
    {
        // Une erreur de traitement ne doit jamais remonter en 5xx : Meta
        // rejouerait l'événement en boucle pendant plusieurs jours.
        $payload = ['entry' => [['changes' => [['value' => ['messages' => 'inattendu']]]]]];

        $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', json_encode($payload), self::SECRET),
        ])->assertOk();
    }
}
