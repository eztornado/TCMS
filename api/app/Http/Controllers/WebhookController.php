<?php

namespace App\Http\Controllers;

use App\Services\Shop\CheckoutService;
use App\Services\Shop\StripeGateway;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhooks de pasarelas: fuente de verdad de la confirmación de pago, con
 * verificación de firma e idempotencia (en quantumcards el webhook estaba
 * vacío y la confirmación era un redirect sin firma).
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, CheckoutService $checkout, StripeGateway $stripe): Response
    {
        if ($provider !== $stripe->name()) {
            abort(404);
        }

        $event = $stripe->verifyWebhook(
            $request->getContent(),
            $request->header('Stripe-Signature', ''),
        );

        $checkout->handleWebhook($event, $provider);

        return response('ok', 200);
    }
}
