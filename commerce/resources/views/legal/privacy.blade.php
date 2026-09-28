@extends('legal.layout')

@section('title', 'Politique de confidentialité')

@section('content')
    <h1>Politique de confidentialité</h1>
    <p class="updated">Dernière mise à jour : {{ \Illuminate\Support\Carbon::now()->translatedFormat('d F Y') }}</p>

    <p>
        La présente politique de confidentialité décrit comment <strong>{{ \App\Support\Shop::name() }}</strong>
        (« nous », « notre », « le site ») collecte, utilise et protège vos données personnelles lorsque
        vous utilisez notre boutique en ligne accessible à l'adresse
        <a href="{{ \App\Support\Shop::url() }}">{{ preg_replace('#^https?://#', '', \App\Support\Shop::url()) }}</a>.
        En utilisant notre site, vous acceptez les pratiques décrites ci-dessous.
    </p>

    <h2>1. Responsable du traitement</h2>
    <p>
        {{ \App\Support\Shop::name() }} est responsable du traitement de vos données personnelles.
        Pour toute question, vous pouvez nous contacter à l'adresse :
        <a href="mailto:{{ \App\Support\Shop::email() }}">{{ \App\Support\Shop::email() }}</a>.
    </p>

    <h2>2. Données que nous collectons</h2>
    <ul>
        <li><strong>Données d'identité et de contact</strong> : nom, prénom, adresse e-mail, numéro de téléphone.</li>
        <li><strong>Données de compte</strong> : identifiants de connexion, photo de profil (avatar).</li>
        <li><strong>Données de connexion sociale</strong> : lorsque vous vous connectez via Facebook, nous recevons
            votre nom, votre adresse e-mail et votre photo de profil publique, conformément aux autorisations que vous accordez.</li>
        <li><strong>Données de commande</strong> : produits commandés, adresse de livraison et de facturation, historique d'achats.</li>
        <li><strong>Données de paiement</strong> : traitées de manière sécurisée par notre prestataire de paiement ;
            nous ne stockons pas vos numéros de carte bancaire.</li>
        <li><strong>Données techniques</strong> : adresse IP, type de navigateur, pages consultées, cookies.</li>
    </ul>

    <h2>3. Finalités d'utilisation</h2>
    <p>Nous utilisons vos données pour :</p>
    <ul>
        <li>créer et gérer votre compte client ;</li>
        <li>traiter, livrer et suivre vos commandes ;</li>
        <li>gérer les paiements et prévenir la fraude ;</li>
        <li>vous fournir un service client et répondre à vos demandes ;</li>
        <li>vous envoyer des informations sur votre commande et, avec votre consentement, des offres commerciales ;</li>
        <li>améliorer notre site et nos services ;</li>
        <li>respecter nos obligations légales et comptables.</li>
    </ul>

    <h2>4. Connexion via Facebook</h2>
    <div class="note">
        Lorsque vous choisissez de vous connecter avec Facebook, nous récupérons uniquement les informations
        de base de votre profil public (nom, adresse e-mail, photo) afin de créer ou d'identifier votre compte.
        Nous ne publions jamais sur votre compte Facebook et n'accédons pas à votre liste d'amis ni à vos
        publications. Vous pouvez révoquer cet accès à tout moment depuis les paramètres de votre compte Facebook.
    </div>

    <h2>5. Partage des données</h2>
    <p>Nous ne vendons jamais vos données. Nous les partageons uniquement avec :</p>
    <ul>
        <li>nos prestataires de paiement, de livraison et d'hébergement, strictement pour l'exécution de votre commande ;</li>
        <li>les autorités compétentes lorsque la loi l'exige.</li>
    </ul>

    <h2>6. Durée de conservation</h2>
    <p>
        Nous conservons vos données aussi longtemps que votre compte est actif et pendant la durée nécessaire au
        respect de nos obligations légales (notamment comptables et fiscales). Au-delà, elles sont supprimées ou anonymisées.
    </p>

    <h2>7. Sécurité</h2>
    <p>
        Nous mettons en œuvre des mesures techniques et organisationnelles appropriées (chiffrement HTTPS, accès
        restreint, sauvegardes) pour protéger vos données contre tout accès, modification ou divulgation non autorisés.
    </p>

    <h2>8. Vos droits</h2>
    <p>Conformément à la réglementation applicable, vous disposez des droits suivants :</p>
    <ul>
        <li>droit d'accès à vos données ;</li>
        <li>droit de rectification ;</li>
        <li>droit à l'effacement (« droit à l'oubli ») ;</li>
        <li>droit d'opposition et de limitation du traitement ;</li>
        <li>droit à la portabilité de vos données ;</li>
        <li>droit de retirer votre consentement à tout moment.</li>
    </ul>
    <p>
        Pour exercer ces droits, écrivez-nous à
        <a href="mailto:{{ \App\Support\Shop::email() }}">{{ \App\Support\Shop::email() }}</a>.
        Pour supprimer vos données, consultez notre page
        <a href="/api/suppression-donnees">Suppression des données</a>.
    </p>

    <h2>9. Cookies</h2>
    <p>
        Notre site utilise des cookies nécessaires à son fonctionnement (session, panier) ainsi que des cookies de
        mesure d'audience. Vous pouvez configurer votre navigateur pour les refuser, ce qui peut limiter certaines fonctionnalités.
    </p>

    <h2>10. Modifications</h2>
    <p>
        Nous pouvons mettre à jour cette politique de confidentialité. Toute modification sera publiée sur cette page
        avec une nouvelle date de mise à jour.
    </p>

    <h2>11. Contact</h2>
    <p>
        Pour toute question relative à cette politique ou au traitement de vos données :
        <a href="mailto:{{ \App\Support\Shop::email() }}">{{ \App\Support\Shop::email() }}</a>.
    </p>
@endsection
