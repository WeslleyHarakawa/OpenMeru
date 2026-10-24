<?php

namespace App\Support;

/**
 * Text of the demo mailbox's sample mail, in every interface language.
 * Each entry is [subject, body]; {domain} becomes MAIL_DOMAIN.
 */
class DemoContent
{
    public const KEYS = ['welcome', 'planning', 'cloudflare', 'proposal', 'brevo', 'photos', 'invoice', 'meeting', 'sophie', 'ideas', 'thanks', 'spam'];

    /** @return array{0: string, 1: string}|null */
    public static function get(string $key, string $locale): ?array
    {
        $all = self::TEXT[$locale] ?? self::TEXT['en'];
        $item = $all[$key] ?? self::TEXT['en'][$key] ?? null;
        if (! $item) {
            return null;
        }
        $domain = (string) config('inbound.domain');

        return [str_replace('{domain}', $domain, $item[0]), str_replace('{domain}', $domain, $item[1])];
    }

    private const TEXT = [
        'en' => [
            'welcome' => ['Welcome to OpenMeru Mail 👋', "Hi!\n\nThis is the OpenMeru demo mailbox. Look around: folders, search, the rich editor, settings, themes and the 10 languages.\n\nIn the demo nothing is sent or saved, so feel free to click anything.\n\nTo run OpenMeru on your own domain, follow the guide at https://{domain}/#install.\n\nThe OpenMeru team"],
            'planning' => ['Q4 planning: notes from today', "Hi,\n\nHere are the notes from today's call:\n\n1. Launch the new landing page next week.\n2. Move the newsletter to the 1st of each month.\n3. Review the support inbox every morning.\n\nLet me know if I missed anything.\n\nMaria"],
            'cloudflare' => ['Email Routing is active for {domain}', "Email Routing is now active.\n\nCatch-all address: Send to a Worker (openmeru-mail-inbound).\n\nMessages to any address on your domain are now handled by your Worker."],
            'proposal' => ['Re: Proposal for the website redesign', "Thanks for the proposal, it looks great.\n\nCan we schedule a quick call on Thursday to go over the timeline?\n\nBest,\nJohn"],
            'brevo' => ['Your domain {domain} is authenticated', "Good news! The DNS records for {domain} were verified.\n\nDKIM: verified\nDMARC: verified\nBrevo code: verified\n\nYou can now send email from any address on this domain."],
            'photos' => ['Photos from the event 📸', "Hi!\n\nThe photos from Saturday's event are ready. I'll share the full album later today.\n\nAna"],
            'invoice' => ['Invoice #2026-104', "Hello,\n\nPlease find the invoice details below:\n\nInvoice: #2026-104\nAmount: €480.00\nDue: in 15 days\n\nThank you for your business.\nLi Wei"],
            'meeting' => ['Meeting on Thursday', "Hi John,\n\nThursday at 10:00 works for me. I'll send the invite.\n\nSee you then!"],
            'sophie' => ['Welcome aboard, Sophie!', "Hi Sophie,\n\nWelcome to the team! Your mailbox is ready: sophie.martin@{domain}.\n\nLet me know if you need anything."],
            'ideas' => ['Ideas for the newsletter', "- Feature of the month: push notifications\n- Tip: auto-forwarding\n- Community showcase"],
            'thanks' => ['Thanks for the welcome!', "Thank you! Happy to be here.\n\nSophie"],
            'spam' => ['You have WON €1,000,000!!!', 'Click here to claim your prize now.'],
        ],
        'pt_BR' => [
            'welcome' => ['Boas-vindas ao OpenMeru Mail 👋', "Oi!\n\nEsta é a caixa demo do OpenMeru. Explore à vontade: pastas, busca, o editor, configurações, temas e os 10 idiomas.\n\nNa demo nada é enviado ou salvo, então pode clicar em tudo.\n\nPara usar o OpenMeru no seu próprio domínio, siga o guia em https://{domain}/#install.\n\nEquipe OpenMeru"],
            'planning' => ['Planejamento do 4º trimestre: anotações de hoje', "Oi,\n\nSeguem as anotações da reunião de hoje:\n\n1. Lançar a nova landing page na semana que vem.\n2. Passar a newsletter para o dia 1º de cada mês.\n3. Revisar a caixa de suporte todas as manhãs.\n\nMe avise se esqueci algo.\n\nMaria"],
            'cloudflare' => ['Email Routing ativo para {domain}', "O Email Routing está ativo.\n\nEndereço catch-all: Enviar para um Worker (openmeru-mail-inbound).\n\nAs mensagens para qualquer endereço do seu domínio agora passam pelo seu Worker."],
            'proposal' => ['Re: Proposta para o novo site', "Obrigado pela proposta, ficou ótima.\n\nPodemos marcar uma ligação rápida na quinta para ver o cronograma?\n\nAbraço,\nJohn"],
            'brevo' => ['Seu domínio {domain} foi autenticado', "Boa notícia! Os registros DNS de {domain} foram verificados.\n\nDKIM: verificado\nDMARC: verificado\nCódigo Brevo: verificado\n\nAgora você pode enviar e-mails de qualquer endereço deste domínio."],
            'photos' => ['Fotos do evento 📸', "Oi!\n\nAs fotos do evento de sábado estão prontas. Compartilho o álbum completo mais tarde.\n\nAna"],
            'invoice' => ['Fatura nº 2026-104', "Olá,\n\nSeguem os dados da fatura:\n\nFatura: nº 2026-104\nValor: € 480,00\nVencimento: em 15 dias\n\nObrigado pela parceria.\nLi Wei"],
            'meeting' => ['Reunião na quinta', "Oi John,\n\nQuinta às 10:00 funciona para mim. Vou mandar o convite.\n\nAté lá!"],
            'sophie' => ['Seja bem-vinda, Sophie!', "Oi Sophie,\n\nBem-vinda à equipe! Sua caixa já está pronta: sophie.martin@{domain}.\n\nQualquer coisa, é só falar."],
            'ideas' => ['Ideias para a newsletter', "- Recurso do mês: notificações push\n- Dica: redirecionamento automático\n- Destaques da comunidade"],
            'thanks' => ['Obrigada pelas boas-vindas!', "Obrigada! Feliz por estar aqui.\n\nSophie"],
            'spam' => ['Você GANHOU € 1.000.000!!!', 'Clique aqui para resgatar seu prêmio agora.'],
        ],
        'pt' => [
            'welcome' => ['Bem-vindo ao OpenMeru Mail 👋', "Olá!\n\nEsta é a caixa demo do OpenMeru. Explora à vontade: pastas, pesquisa, o editor, definições, temas e os 10 idiomas.\n\nNa demo nada é enviado nem guardado, por isso podes clicar em tudo.\n\nPara usar o OpenMeru no teu próprio domínio, segue o guia em https://{domain}/#install.\n\nA equipa OpenMeru"],
            'planning' => ['Planeamento do 4.º trimestre: notas de hoje', "Olá,\n\nAqui ficam as notas da reunião de hoje:\n\n1. Lançar a nova página inicial na próxima semana.\n2. Passar a newsletter para o dia 1 de cada mês.\n3. Rever a caixa de suporte todas as manhãs.\n\nAvisa-me se me esqueci de alguma coisa.\n\nMaria"],
            'cloudflare' => ['Email Routing ativo para {domain}', "O Email Routing está ativo.\n\nEndereço catch-all: Enviar para um Worker (openmeru-mail-inbound).\n\nAs mensagens para qualquer endereço do teu domínio passam agora pelo teu Worker."],
            'proposal' => ['Re: Proposta para o novo site', "Obrigado pela proposta, está ótima.\n\nPodemos marcar uma chamada rápida na quinta para ver o calendário?\n\nCumprimentos,\nJohn"],
            'brevo' => ['O teu domínio {domain} está autenticado', "Boas notícias! Os registos DNS de {domain} foram verificados.\n\nDKIM: verificado\nDMARC: verificado\nCódigo Brevo: verificado\n\nJá podes enviar e-mails de qualquer endereço deste domínio."],
            'photos' => ['Fotos do evento 📸', "Olá!\n\nAs fotos do evento de sábado estão prontas. Partilho o álbum completo mais logo.\n\nAna"],
            'invoice' => ['Fatura n.º 2026-104', "Olá,\n\nSeguem os dados da fatura:\n\nFatura: n.º 2026-104\nValor: 480,00 €\nVencimento: dentro de 15 dias\n\nObrigado pela preferência.\nLi Wei"],
            'meeting' => ['Reunião na quinta', "Olá John,\n\nQuinta às 10:00 dá-me jeito. Vou enviar o convite.\n\nAté lá!"],
            'sophie' => ['Bem-vinda, Sophie!', "Olá Sophie,\n\nBem-vinda à equipa! A tua caixa já está pronta: sophie.martin@{domain}.\n\nSe precisares de alguma coisa, diz."],
            'ideas' => ['Ideias para a newsletter', "- Funcionalidade do mês: notificações push\n- Dica: reencaminhamento automático\n- Destaques da comunidade"],
            'thanks' => ['Obrigada pelas boas-vindas!', "Obrigada! Muito contente por estar cá.\n\nSophie"],
            'spam' => ['GANHASTE 1.000.000 €!!!', 'Clica aqui para receber o teu prémio já.'],
        ],
        'es' => [
            'welcome' => ['Bienvenido a OpenMeru Mail 👋', "¡Hola!\n\nEste es el buzón demo de OpenMeru. Explora con libertad: carpetas, búsqueda, el editor, ajustes, temas y los 10 idiomas.\n\nEn la demo no se envía ni se guarda nada, así que puedes hacer clic en todo.\n\nPara usar OpenMeru en tu propio dominio, sigue la guía en https://{domain}/#install.\n\nEl equipo de OpenMeru"],
            'planning' => ['Planificación del 4.º trimestre: notas de hoy', "Hola,\n\nAquí van las notas de la reunión de hoy:\n\n1. Lanzar la nueva landing page la próxima semana.\n2. Pasar la newsletter al día 1 de cada mes.\n3. Revisar el buzón de soporte cada mañana.\n\nAvísame si se me escapó algo.\n\nMaria"],
            'cloudflare' => ['Email Routing activo para {domain}', "Email Routing ya está activo.\n\nDirección catch-all: Enviar a un Worker (openmeru-mail-inbound).\n\nLos mensajes a cualquier dirección de tu dominio ahora pasan por tu Worker."],
            'proposal' => ['Re: Propuesta para el nuevo sitio web', "Gracias por la propuesta, se ve genial.\n\n¿Podemos hacer una llamada rápida el jueves para revisar los plazos?\n\nSaludos,\nJohn"],
            'brevo' => ['Tu dominio {domain} está autenticado', "¡Buenas noticias! Los registros DNS de {domain} se verificaron.\n\nDKIM: verificado\nDMARC: verificado\nCódigo Brevo: verificado\n\nYa puedes enviar correos desde cualquier dirección de este dominio."],
            'photos' => ['Fotos del evento 📸', "¡Hola!\n\nLas fotos del evento del sábado están listas. Compartiré el álbum completo más tarde.\n\nAna"],
            'invoice' => ['Factura n.º 2026-104', "Hola,\n\nEstos son los datos de la factura:\n\nFactura: n.º 2026-104\nImporte: 480,00 €\nVencimiento: en 15 días\n\nGracias por su confianza.\nLi Wei"],
            'meeting' => ['Reunión el jueves', "Hola John,\n\nEl jueves a las 10:00 me va bien. Te envío la invitación.\n\n¡Nos vemos!"],
            'sophie' => ['¡Bienvenida, Sophie!', "Hola Sophie,\n\n¡Bienvenida al equipo! Tu buzón ya está listo: sophie.martin@{domain}.\n\nAvísame si necesitas algo."],
            'ideas' => ['Ideas para la newsletter', "- Función del mes: notificaciones push\n- Consejo: reenvío automático\n- Lo mejor de la comunidad"],
            'thanks' => ['¡Gracias por la bienvenida!', "¡Gracias! Encantada de estar aquí.\n\nSophie"],
            'spam' => ['¡¡¡Has GANADO 1.000.000 €!!!', 'Haz clic aquí para reclamar tu premio ahora.'],
        ],
        'fr' => [
            'welcome' => ['Bienvenue sur OpenMeru Mail 👋', "Bonjour !\n\nVoici la boîte démo d'OpenMeru. Explorez librement : dossiers, recherche, l'éditeur, paramètres, thèmes et les 10 langues.\n\nDans la démo, rien n'est envoyé ni enregistré : cliquez sur tout ce que vous voulez.\n\nPour utiliser OpenMeru sur votre propre domaine, suivez le guide sur https://{domain}/#install.\n\nL'équipe OpenMeru"],
            'planning' => ['Planification du T4 : notes du jour', "Bonjour,\n\nVoici les notes de la réunion d'aujourd'hui :\n\n1. Lancer la nouvelle page d'accueil la semaine prochaine.\n2. Passer la newsletter au 1er de chaque mois.\n3. Vérifier la boîte support chaque matin.\n\nDites-moi si j'ai oublié quelque chose.\n\nMaria"],
            'cloudflare' => ['Email Routing est actif pour {domain}', "Email Routing est maintenant actif.\n\nAdresse catch-all : Envoyer vers un Worker (openmeru-mail-inbound).\n\nLes messages vers n'importe quelle adresse de votre domaine passent désormais par votre Worker."],
            'proposal' => ['Re : Proposition pour la refonte du site', "Merci pour la proposition, elle est très bien.\n\nPeut-on prévoir un court appel jeudi pour revoir le calendrier ?\n\nCordialement,\nJohn"],
            'brevo' => ['Votre domaine {domain} est authentifié', "Bonne nouvelle ! Les enregistrements DNS de {domain} ont été vérifiés.\n\nDKIM : vérifié\nDMARC : vérifié\nCode Brevo : vérifié\n\nVous pouvez maintenant envoyer des e-mails depuis n'importe quelle adresse de ce domaine."],
            'photos' => ["Photos de l'événement 📸", "Bonjour !\n\nLes photos de l'événement de samedi sont prêtes. Je partage l'album complet plus tard.\n\nAna"],
            'invoice' => ['Facture n° 2026-104', "Bonjour,\n\nVoici les détails de la facture :\n\nFacture : n° 2026-104\nMontant : 480,00 €\nÉchéance : sous 15 jours\n\nMerci pour votre confiance.\nLi Wei"],
            'meeting' => ['Réunion jeudi', "Bonjour John,\n\nJeudi à 10h00 me convient. J'envoie l'invitation.\n\nÀ jeudi !"],
            'sophie' => ['Bienvenue, Sophie !', "Bonjour Sophie,\n\nBienvenue dans l'équipe ! Ta boîte est prête : sophie.martin@{domain}.\n\nN'hésite pas si tu as besoin de quoi que ce soit."],
            'ideas' => ['Idées pour la newsletter', "- Fonction du mois : notifications push\n- Astuce : transfert automatique\n- Coups de cœur de la communauté"],
            'thanks' => ["Merci pour l'accueil !", "Merci ! Ravie d'être là.\n\nSophie"],
            'spam' => ['Vous avez GAGNÉ 1 000 000 € !!!', 'Cliquez ici pour réclamer votre prix maintenant.'],
        ],
        'de' => [
            'welcome' => ['Willkommen bei OpenMeru Mail 👋', "Hallo!\n\nDas ist das Demo-Postfach von OpenMeru. Schau dich um: Ordner, Suche, der Editor, Einstellungen, Designs und die 10 Sprachen.\n\nIn der Demo wird nichts gesendet oder gespeichert, du kannst also alles anklicken.\n\nUm OpenMeru auf deiner eigenen Domain zu nutzen, folge der Anleitung unter https://{domain}/#install.\n\nDas OpenMeru-Team"],
            'planning' => ['Q4-Planung: Notizen von heute', "Hallo,\n\nhier die Notizen vom heutigen Call:\n\n1. Die neue Landingpage nächste Woche starten.\n2. Den Newsletter auf den 1. jedes Monats verlegen.\n3. Das Support-Postfach jeden Morgen prüfen.\n\nSag Bescheid, falls ich etwas vergessen habe.\n\nMaria"],
            'cloudflare' => ['Email Routing ist aktiv für {domain}', "Email Routing ist jetzt aktiv.\n\nCatch-all-Adresse: An einen Worker senden (openmeru-mail-inbound).\n\nNachrichten an jede Adresse deiner Domain laufen jetzt über deinen Worker."],
            'proposal' => ['Re: Angebot für den Website-Relaunch', "Danke für das Angebot, sieht super aus.\n\nKönnen wir am Donnerstag kurz telefonieren, um den Zeitplan durchzugehen?\n\nViele Grüße\nJohn"],
            'brevo' => ['Deine Domain {domain} ist authentifiziert', "Gute Nachrichten! Die DNS-Einträge für {domain} wurden bestätigt.\n\nDKIM: bestätigt\nDMARC: bestätigt\nBrevo-Code: bestätigt\n\nDu kannst jetzt von jeder Adresse dieser Domain E-Mails senden."],
            'photos' => ['Fotos vom Event 📸', "Hallo!\n\nDie Fotos vom Event am Samstag sind fertig. Ich teile später das ganze Album.\n\nAna"],
            'invoice' => ['Rechnung Nr. 2026-104', "Hallo,\n\nhier die Rechnungsdaten:\n\nRechnung: Nr. 2026-104\nBetrag: 480,00 €\nFällig: in 15 Tagen\n\nVielen Dank für Ihren Auftrag.\nLi Wei"],
            'meeting' => ['Termin am Donnerstag', "Hallo John,\n\nDonnerstag um 10:00 passt mir. Ich schicke die Einladung.\n\nBis dann!"],
            'sophie' => ['Willkommen an Bord, Sophie!', "Hallo Sophie,\n\nwillkommen im Team! Dein Postfach ist bereit: sophie.martin@{domain}.\n\nMelde dich, wenn du etwas brauchst."],
            'ideas' => ['Ideen für den Newsletter', "- Funktion des Monats: Push-Benachrichtigungen\n- Tipp: automatische Weiterleitung\n- Highlights aus der Community"],
            'thanks' => ['Danke für den Empfang!', "Danke! Ich freue mich, dabei zu sein.\n\nSophie"],
            'spam' => ['Sie haben 1.000.000 € GEWONNEN!!!', 'Klicken Sie hier, um Ihren Gewinn jetzt abzuholen.'],
        ],
        'it' => [
            'welcome' => ['Benvenuto in OpenMeru Mail 👋', "Ciao!\n\nQuesta è la casella demo di OpenMeru. Esplora liberamente: cartelle, ricerca, l'editor, impostazioni, temi e le 10 lingue.\n\nNella demo non viene inviato né salvato nulla, quindi puoi cliccare ovunque.\n\nPer usare OpenMeru sul tuo dominio, segui la guida su https://{domain}/#install.\n\nIl team di OpenMeru"],
            'planning' => ['Pianificazione Q4: appunti di oggi', "Ciao,\n\necco gli appunti della call di oggi:\n\n1. Lanciare la nuova landing page la prossima settimana.\n2. Spostare la newsletter al 1° di ogni mese.\n3. Controllare la casella di supporto ogni mattina.\n\nFammi sapere se ho dimenticato qualcosa.\n\nMaria"],
            'cloudflare' => ['Email Routing è attivo per {domain}', "Email Routing è ora attivo.\n\nIndirizzo catch-all: Invia a un Worker (openmeru-mail-inbound).\n\nI messaggi a qualsiasi indirizzo del tuo dominio passano ora dal tuo Worker."],
            'proposal' => ['Re: Proposta per il nuovo sito', "Grazie per la proposta, è ottima.\n\nPossiamo fare una breve call giovedì per vedere le tempistiche?\n\nA presto,\nJohn"],
            'brevo' => ['Il tuo dominio {domain} è autenticato', "Buone notizie! I record DNS di {domain} sono stati verificati.\n\nDKIM: verificato\nDMARC: verificato\nCodice Brevo: verificato\n\nOra puoi inviare email da qualsiasi indirizzo di questo dominio."],
            'photos' => ["Foto dell'evento 📸", "Ciao!\n\nLe foto dell'evento di sabato sono pronte. Più tardi condivido l'album completo.\n\nAna"],
            'invoice' => ['Fattura n. 2026-104', "Salve,\n\necco i dettagli della fattura:\n\nFattura: n. 2026-104\nImporto: 480,00 €\nScadenza: entro 15 giorni\n\nGrazie per la fiducia.\nLi Wei"],
            'meeting' => ['Riunione giovedì', "Ciao John,\n\ngiovedì alle 10:00 per me va bene. Ti mando l'invito.\n\nA giovedì!"],
            'sophie' => ['Benvenuta a bordo, Sophie!', "Ciao Sophie,\n\nbenvenuta nel team! La tua casella è pronta: sophie.martin@{domain}.\n\nFammi sapere se ti serve qualcosa."],
            'ideas' => ['Idee per la newsletter', "- Funzione del mese: notifiche push\n- Consiglio: inoltro automatico\n- Il meglio dalla community"],
            'thanks' => ["Grazie per l'accoglienza!", "Grazie! Felice di essere qui.\n\nSophie"],
            'spam' => ['Hai VINTO 1.000.000 €!!!', 'Clicca qui per riscattare subito il tuo premio.'],
        ],
        'nl' => [
            'welcome' => ['Welkom bij OpenMeru Mail 👋', "Hoi!\n\nDit is de demo-mailbox van OpenMeru. Kijk gerust rond: mappen, zoeken, de editor, instellingen, thema's en de 10 talen.\n\nIn de demo wordt niets verzonden of opgeslagen, dus klik gerust overal op.\n\nWil je OpenMeru op je eigen domein gebruiken? Volg de handleiding op https://{domain}/#install.\n\nHet OpenMeru-team"],
            'planning' => ['Planning Q4: notities van vandaag', "Hoi,\n\nHier zijn de notities van het overleg van vandaag:\n\n1. Volgende week de nieuwe landingspagina lanceren.\n2. De nieuwsbrief verplaatsen naar de 1e van elke maand.\n3. Elke ochtend de supportmailbox nakijken.\n\nLaat het weten als ik iets vergeten ben.\n\nMaria"],
            'cloudflare' => ['Email Routing is actief voor {domain}', "Email Routing is nu actief.\n\nCatch-all-adres: Verzenden naar een Worker (openmeru-mail-inbound).\n\nBerichten naar elk adres op je domein gaan nu via je Worker."],
            'proposal' => ['Re: Voorstel voor de nieuwe website', "Bedankt voor het voorstel, het ziet er goed uit.\n\nKunnen we donderdag even bellen om de planning door te nemen?\n\nGroet,\nJohn"],
            'brevo' => ['Je domein {domain} is geverifieerd', "Goed nieuws! De DNS-records van {domain} zijn geverifieerd.\n\nDKIM: geverifieerd\nDMARC: geverifieerd\nBrevo-code: geverifieerd\n\nJe kunt nu e-mail versturen vanaf elk adres op dit domein."],
            'photos' => ["Foto's van het evenement 📸", "Hoi!\n\nDe foto's van het evenement van zaterdag zijn klaar. Ik deel later vandaag het hele album.\n\nAna"],
            'invoice' => ['Factuur nr. 2026-104', "Hallo,\n\nHierbij de factuurgegevens:\n\nFactuur: nr. 2026-104\nBedrag: € 480,00\nVervaldatum: binnen 15 dagen\n\nBedankt voor uw opdracht.\nLi Wei"],
            'meeting' => ['Afspraak op donderdag', "Hoi John,\n\nDonderdag om 10:00 komt mij goed uit. Ik stuur de uitnodiging.\n\nTot dan!"],
            'sophie' => ['Welkom aan boord, Sophie!', "Hoi Sophie,\n\nWelkom in het team! Je mailbox staat klaar: sophie.martin@{domain}.\n\nLaat het weten als je iets nodig hebt."],
            'ideas' => ['Ideeën voor de nieuwsbrief', "- Functie van de maand: pushmeldingen\n- Tip: automatisch doorsturen\n- Uitgelicht uit de community"],
            'thanks' => ['Bedankt voor het welkom!', "Dank je! Fijn om hier te zijn.\n\nSophie"],
            'spam' => ['U heeft € 1.000.000 GEWONNEN!!!', 'Klik hier om uw prijs nu te claimen.'],
        ],
        'ru' => [
            'welcome' => ['Добро пожаловать в OpenMeru Mail 👋', "Привет!\n\nЭто демо-ящик OpenMeru. Осмотритесь: папки, поиск, редактор, настройки, темы и 10 языков.\n\nВ демо ничего не отправляется и не сохраняется, так что нажимайте что угодно.\n\nЧтобы запустить OpenMeru на своём домене, следуйте инструкции на https://{domain}/#install.\n\nКоманда OpenMeru"],
            'planning' => ['Планы на 4-й квартал: заметки с сегодняшней встречи', "Привет,\n\nВот заметки с сегодняшнего созвона:\n\n1. Запустить новую главную страницу на следующей неделе.\n2. Перенести рассылку на 1-е число каждого месяца.\n3. Каждое утро проверять ящик поддержки.\n\nДай знать, если я что-то упустила.\n\nМария"],
            'cloudflare' => ['Email Routing включён для {domain}', "Email Routing теперь включён.\n\nCatch-all адрес: Отправлять в Worker (openmeru-mail-inbound).\n\nПисьма на любой адрес вашего домена теперь обрабатывает ваш Worker."],
            'proposal' => ['Re: Предложение по редизайну сайта', "Спасибо за предложение, выглядит отлично.\n\nМожем коротко созвониться в четверг и обсудить сроки?\n\nС уважением,\nДжон"],
            'brevo' => ['Ваш домен {domain} подтверждён', "Хорошие новости! DNS-записи для {domain} подтверждены.\n\nDKIM: подтверждено\nDMARC: подтверждено\nКод Brevo: подтверждено\n\nТеперь можно отправлять письма с любого адреса этого домена."],
            'photos' => ['Фото с мероприятия 📸', "Привет!\n\nФото с субботнего мероприятия готовы. Позже поделюсь всем альбомом.\n\nАна"],
            'invoice' => ['Счёт № 2026-104', "Здравствуйте,\n\nДанные счёта:\n\nСчёт: № 2026-104\nСумма: 480,00 €\nСрок оплаты: 15 дней\n\nСпасибо за сотрудничество.\nЛи Вэй"],
            'meeting' => ['Встреча в четверг', "Привет, Джон,\n\nЧетверг в 10:00 мне подходит. Отправлю приглашение.\n\nДо встречи!"],
            'sophie' => ['Добро пожаловать, Софи!', "Привет, Софи,\n\nДобро пожаловать в команду! Твой ящик готов: sophie.martin@{domain}.\n\nПиши, если что-то понадобится."],
            'ideas' => ['Идеи для рассылки', "- Функция месяца: push-уведомления\n- Совет: автопересылка\n- Лучшее от сообщества"],
            'thanks' => ['Спасибо за тёплый приём!', "Спасибо! Рада быть здесь.\n\nСофи"],
            'spam' => ['Вы ВЫИГРАЛИ 1 000 000 €!!!', 'Нажмите здесь, чтобы получить приз прямо сейчас.'],
        ],
        'zh' => [
            'welcome' => ['欢迎使用 OpenMeru Mail 👋', "你好！\n\n这是 OpenMeru 的演示邮箱。随意看看：文件夹、搜索、编辑器、设置、主题以及 10 种语言。\n\n演示中不会发送或保存任何内容，所以尽管点。\n\n想在自己的域名上运行 OpenMeru，请按照 https://{domain}/#install 上的指南操作。\n\nOpenMeru 团队"],
            'planning' => ['第四季度计划：今天的会议记录', "你好，\n\n以下是今天会议的记录：\n\n1. 下周上线新的落地页。\n2. 把月刊改到每月 1 号发送。\n3. 每天早上查看支持邮箱。\n\n如有遗漏请告诉我。\n\nMaria"],
            'cloudflare' => ['{domain} 的 Email Routing 已启用', "Email Routing 现已启用。\n\nCatch-all 地址：发送到 Worker（openmeru-mail-inbound）。\n\n发往你域名下任何地址的邮件现在都由你的 Worker 处理。"],
            'proposal' => ['回复：网站改版方案', "谢谢你的方案，看起来很棒。\n\n周四可以简单通个电话，一起过一下时间安排吗？\n\n此致\nJohn"],
            'brevo' => ['你的域名 {domain} 已通过验证', "好消息！{domain} 的 DNS 记录已通过验证。\n\nDKIM：已验证\nDMARC：已验证\nBrevo 代码：已验证\n\n现在你可以用这个域名下的任何地址发送邮件了。"],
            'photos' => ['活动照片 📸', "你好！\n\n周六活动的照片已经整理好了，今天晚些时候分享完整相册。\n\nAna"],
            'invoice' => ['发票 #2026-104', "你好，\n\n发票信息如下：\n\n发票：#2026-104\n金额：€480.00\n到期：15 天内\n\n感谢惠顾。\nLi Wei"],
            'meeting' => ['周四开会', "John 你好，\n\n周四 10:00 我可以。我来发邀请。\n\n到时见！"],
            'sophie' => ['欢迎加入，Sophie！', "Sophie 你好，\n\n欢迎加入团队！你的邮箱已经准备好了：sophie.martin@{domain}。\n\n有需要随时告诉我。"],
            'ideas' => ['月刊的点子', "- 本月功能：推送通知\n- 小技巧：自动转发\n- 社区精选"],
            'thanks' => ['谢谢你们的欢迎！', "谢谢！很高兴加入。\n\nSophie"],
            'spam' => ['你赢得了 €1,000,000！！！', '立即点击这里领取奖金。'],
        ],
    ];
}
