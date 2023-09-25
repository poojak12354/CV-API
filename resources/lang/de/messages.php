<?php

return [

   'account_address' => [
      'company_name'=>'Das Feld Firmenname ist erforderlich.',
      'gender'=>'Das Geschlecht ist erforderlich',
      'first_name'=>'Das Feld Vorname ist erforderlich.',
      'last_name'=>'Das Feld Nachname ist erforderlich.',
      'street_address'=>'Das Straßenadressenfeld ist erforderlich.',
      'street_number'=>'Das Feld Straßennummer ist erforderlich.',
      'pin_code'=>'Das PIN-Code-Feld ist erforderlich.',
      'location'=>'Das Ortsfeld ist erforderlich.',
      'country'=>'Das Land wird benötigt',
   ],

   'auth_code_expire' => 'Dieser Code ist abgelaufen',


   'login'=>[
      'limit_reached'=>'Sie haben die Grenze der fehlerhaften Anmeldung erreicht, versuchen Sie es nach einiger Zeit erneut'
   ],
       
   'wrong'=>'Etwas ist schief gegangen',

   'file'=>[
      'success'=>'Datei wurde hochgeladen.',
      'delete'=>'Datei wurde gelöscht.'

   ],

   

   'user'=>[

      'exists'=>'Dieser Username existiert bereits.',
      'not_in_team'=>'Dieser User ist nicht in Ihrem Team.'
   ],

 
   'invitation'=>[
      'sent'=>'Die Einladung wurde versandt.'
   ],
   'invite_code'=>[

      'exists'=>'Einladungscode bereits verwendet'
   ],
  'email'=>[

     'required'=>'Die E-Mail ist erforderlich.',
     'exists'=>'Angaben sind nicht korrekt.',
     'unique'=>'Die E-Mail wurde bereits angenommen.',
     'email'=>'Die E-Mail hat ein ungültiges Format.',
     'distinct'=>'Das E-Mail-Feld hat einen doppelten Wert.'

   ],

   'first_name'=>[
       'required'=>'Der Vorname ist erforderlich',
       'min'=>'Mindestens 2 Buchstaben im Vornamen erforderlich'
   ],

   'company_name'=>[
       'required_if'=>'Der Firmenname ist erforderlich'
   ],

   'last_name'=>[
    'required'=>'Der Nachname ist erforderlich',
    'min'=>'Mindestens 2 Buchstaben im Nachnamen erforderlich'
    ],

  "auth_code"=>[

   'required'=>'Der Authentifizierungscode ist erforderlich.'
  ],

  "token"=>[

   'required'=>'Ein Token ist erforderlich.',
   'exists'=>'Dieses Token ist ungültig.',
   'invalid'=>"Ungültiges Authentifizierungs-Token"
  ],
 
  'current_password'=>[

   'required'=>'Das aktuelle Passwort ist erforderlich.',
   'match'=>'Das aktuelle Passwort sollte mit dem alten Passwort übereinstimmen.'

   ], 

  'new_password'=>[

   'required'=>'Das neue Passwort ist erforderlich.',
   'min'=>'Das neue Passwort muss mindestens :MIN Zeichen umfassen.',
   'different'=>'Das neue Passwort sollte sich vom aktuellen Passwort unterscheiden'
   ], 

   'confirm_password'=>[
      'required'=>'Die Bestätigung des Passworts ist erforderlich.',
      'same'=>'Das Bestätigungskennwort und das neue Kennwort müssen übereinstimmen.'
   ], 
  'password'=>[

     'required'=>'Das Passwort ist erforderlich.',
     'min'=>'Das Passwort muss mindestens :MIN Zeichen umfassen.'

  ],

  "cv"=>[
     "street_number" =>"Sólo se permiten letras y números en el número de la calle",
     "already"=>"Ihr Lebenslauf wurde bereits erstellt.",
     "not_rights"=>'Sie haben nicht die Berechtigung, den Lebenslauf zu bearbeiten.',
     "not_exists"=>'Dieser Lebenslauf existiert nicht.',
     "delete" =>'Lebenslaufdaten wurden gelöscht.',
     "deleted"=>'Lebenslaufdaten wurden bereits gelöscht.',
      "first_name"=>[
         "required"=>"Bitte geben Sie Ihren Vornamen ein."
      ],
      "last_name"=>[
         "required"=>'Bitte geben Sie Ihren Nachnamen ein.'
      ],
      "network"=>[
         "required"=>'Bitte geben Sie eine Webadresse ein.'
      ],
      "url"=>[
         "required"=>'Die :network ist erforderlich.',
         'distinct'=>'Doppelte Werte erkannt'
      ],
      "cv_contact_details"=>[

         "email"=>[
            "required"=>'Die Email-Adresse ist erforderlich.',
            "email"=>'Bitte geben Sie eine gültige Email-Adresse ein.'
         ],
         "website"=>[
            "url"=>"Die Webadresse hat kein gültiges Format."
         ]
      ],
      "cv_images"=>[
         "image_id"=>[
            "exists"=>"Die Image Id ist ungültig." 
         ],
         "video_id"=>[
            "exists"=>"Die Video Id ist ungültig."
         ]
      ]

   
  ],
  "unauthorize"=>'Angaben sind nicht korrekt.',

  "record"=>[
   "heading_text"=>"Eine Überschrift ist erforderlich",
   "heading_description"=>"Ein beschreibender Text ist erforderlich",
  ],
  "address"=>[
   "company_name"=>'Bitte geben Sie den Firmennamen ein.',
   'address_designation'=>'Bitte geben Sie die Adresse ein',
   'additive'=>'Bitte geben Sie den Adress-Zusatz ein',
   'road'=>'Bitte geben Sie die Straße ein',
   'road_no'=>'Bitte geben Sie die Hausnummer ein',
   'postcode'=>'Bitte geben Sie die Postleitzahl ein',
   'place'=>'Bitte geben Sie den Ort ein',
   'country'=>'Bitte geben Sie das Land ein',
 
  ],

  'team_id'=>[

      'required'=>'Team-ID ist erforderlich',
      'exists' =>'Team ist nicht im System vorhanden'
  ]
];