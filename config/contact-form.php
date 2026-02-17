<?php

use craft\helpers\App;

return [
    // The email address(es) that the contact form submissions should be sent to
    'toEmail' => App::env('CONTACT_FORM_TO_EMAIL') ?: 'info@medson.com',

    // The email address that the contact form submissions should be sent from
    'fromEmail' => App::env('CONTACT_FORM_FROM_EMAIL') ?: 'noreply@medson.com',

    // The "name" that should be used for the "from" email address
    'fromName' => App::env('CONTACT_FORM_FROM_NAME') ?: 'Medson Contact Form',

    // The subject line of the contact form submission email
    'subject' => 'New contact form submission from Medson website',

    // The success message flash to display on successful submission
    'successFlashMessage' => 'Bedankt voor uw bericht. We nemen zo spoedig mogelijk contact met u op.',

    // The error message flash to display on failed submission
    'errorFlashMessage' => 'Er is iets misgegaan bij het verzenden van uw bericht. Probeer het opnieuw.',

    // The template path to use for notification emails
    // 'templatePath' => '_emails/contact',

    // Whether the plugin should allow attachments to be sent
    'allowAttachments' => false,

    // Enable honeypot field for spam protection
    'honeypotEnabled' => true,
];
