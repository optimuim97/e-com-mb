@extends('legal.layout')

@section('title', 'Suppression des données')

@section('content')
    <h1>Suppression des données utilisateur</h1>
    <p class="updated">Dernière mise à jour : {{ \Illuminate\Support\Carbon::now()->translatedFormat('d F Y') }}</p>

    <p>
        Chez <strong>{{ \App\Support\Shop::name() }}</strong>, vous gardez le contrôle de vos données personnelles.
        Cette page explique comment demander la suppression des données associées à votre compte, y compris
        celles obtenues lors d'une connexion via Facebook.
    </p>

    <h2>Comment demander la suppression de vos données</h2>
    <p>Vous avez deux possibilités :</p>
    <ul>
        <li>
            <strong>Par e-mail</strong> : envoyez une demande à
            <a href="mailto:{{ \App\Support\Shop::email() }}?subject=Suppression%20de%20mes%20donn%C3%A9es">{{ \App\Support\Shop::email() }}</a>
            depuis l'adresse associée à votre compte, avec pour objet « Suppression de mes données ».
        </li>
        <li>
            <strong>Depuis votre compte</strong> : connectez-vous à votre espace client sur
            <a href="{{ \App\Support\Shop::url() }}">{{ preg_replace('#^https?://#', '', \App\Support\Shop::url()) }}</a> et utilisez l'option de
            suppression de compte, ou contactez notre service client.
        </li>
    </ul>

    <div class="note">
        <strong>Connexion via Facebook :</strong> si vous vous êtes inscrit avec Facebook, vous pouvez également
        retirer l'accès de l'application depuis
        <em>Facebook → Paramètres &amp; confidentialité → Paramètres → Applications et sites web</em>,
        puis nous adresser une demande de suppression via l'une des méthodes ci-dessus.
    </div>

    <h2>Ce qui est supprimé</h2>
    <p>À réception de votre demande, nous supprimons :</p>
    <ul>
        <li>vos données de profil (nom, e-mail, photo, identifiants de connexion) ;</li>
        <li>les données reçues de Facebook lors de votre connexion ;</li>
        <li>les informations de votre compte client.</li>
    </ul>
    <p>
        Certaines données peuvent être conservées de façon limitée lorsque la loi l'impose (par exemple les
        factures, pour des raisons comptables et fiscales). Elles sont alors isolées et ne servent plus qu'à
        cette obligation légale.
    </p>

    <h2>Délai de traitement</h2>
    <p>
        Votre demande est traitée dans un délai maximal de <strong>30 jours</strong>. Une confirmation vous est
        envoyée par e-mail une fois la suppression effectuée.
    </p>

    <h2>Contact</h2>
    <p>
        Pour toute question concernant la suppression de vos données :
        <a href="mailto:{{ \App\Support\Shop::email() }}">{{ \App\Support\Shop::email() }}</a>.
    </p>
@endsection
