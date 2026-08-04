<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerssl_WellKnownController — answers the ACME HTTP-01 domain-control challenge.
 *
 * The certificate authority fetches http://<domain>/.well-known/acme-challenge/<token> anonymously
 * during issuance; we serve it here with a NATIVE route (configs/routes.ini) — no .htaccess, no vhost,
 * no static file (ARCHITECTURE §6). GUEST-allowed by design (configs/acl.ini): the CA has no session.
 *
 * It returns ONLY the key-authorization string for a token that is CURRENTLY PENDING (written by
 * Tigerssl_Service_Certificate during an active issuance, kept in a short-TTL file cache). An unknown or
 * expired token 404s — the endpoint can't enumerate or leak anything.
 */
class Tigerssl_WellKnownController extends Zend_Controller_Action
{
    /**
     * Serve the key authorization for :token as text/plain (HTTP 200), or 404 if no such pending
     * challenge. No layout, no view — the ACME spec wants the raw token contents and nothing else.
     *
     * @return void
     */
    public function challengeAction()
    {
        // This controller renders raw text; disable the view + layout entirely.
        $this->_helper->viewRenderer->setNoRender(true);
        if ($this->_helper->hasHelper('layout')) { $this->_helper->layout()->disableLayout(); }

        $token   = (string) $this->getRequest()->getParam('token', '');
        $keyAuth = Tigerssl_Model_Challenge::get($token);

        $response = $this->getResponse();
        if ($keyAuth === null || $token === '') {
            // Unknown/expired token — a clean 404, no detail.
            $response->setHttpResponseCode(404)
                     ->setHeader('Content-Type', 'text/plain; charset=utf-8', true)
                     ->setBody('Not Found');
            return;
        }

        // Exactly the key authorization, plain text, 200. (RFC 8555 §8.3.)
        $response->setHttpResponseCode(200)
                 ->setHeader('Content-Type', 'text/plain; charset=utf-8', true)
                 ->setHeader('Cache-Control', 'no-store', true)
                 ->setBody($keyAuth);
    }
}
