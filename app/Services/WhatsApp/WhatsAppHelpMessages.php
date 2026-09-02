<?php

namespace App\Services\WhatsApp;

use App\Models\User;

class WhatsAppHelpMessages
{
    public static function help(User $user): string
    {
        $lang = $user->whatsapp_language ?? 'en';

        return match ($lang) {
            'es' => self::helpEs($user),
            'fr' => self::helpFr($user),
            default => self::helpEn($user),
        };
    }

    public static function shortcuts(User $user): string
    {
        $lang = $user->whatsapp_language ?? 'en';

        return match ($lang) {
            'es' => self::shortcutsEs(),
            'fr' => self::shortcutsFr(),
            default => self::shortcutsEn(),
        };
    }

    public static function welcomeLinked(User $user): string
    {
        $lang = $user->whatsapp_language ?? 'en';

        return match ($lang) {
            'es' => "Control remoto activado. Escribe lo que quieras hacer — por ejemplo: *muéstrame mis menciones* o *publica: ¡Hola mundo!*\n\nEnvía *help* para ver más ejemplos.",
            'fr' => "Contrôle à distance activé. Écrivez ce que vous voulez — par ex. *montre mes mentions* ou *poste: Bonjour le monde!*\n\nEnvoyez *help* pour plus d'exemples.",
            default => "Remote control is ON. Just tell me what you want — e.g. *show my mentions* or *post: Hello world!*\n\nSend *help* for more examples.",
        };
    }

    protected static function helpEn(User $user): string
    {
        return implode("\n", [
            '👋 *XEngager on WhatsApp*',
            '',
            'Talk to me in plain English — no codes required.',
            '',
            '📝 *Post & schedule*',
            '• Post: Excited about our launch today!',
            '• Schedule tomorrow 9am | Good morning everyone',
            '• Show my queue',
            '• Give me post ideas',
            '',
            '💬 *Engage on X*',
            '• Show my mentions',
            '• Reply to 1 with Thanks for reaching out!',
            '• Search for #startup',
            '• Show my keywords',
            '',
            '⚙️ *Account*',
            '• Status — connection & bot info',
            '• Settings — your toggles',
            '',
            '💡 Not sure? Ask naturally: *what can I post today?* or *check my mentions*',
            '',
            'Power users: send *shortcuts* for command codes.',
        ]);
    }

    protected static function helpEs(User $user): string
    {
        return implode("\n", [
            '👋 *XEngager en WhatsApp*',
            '',
            'Escríbeme en español normal — sin códigos.',
            '',
            '📝 *Publicar*',
            '• Publica: ¡Lanzamos hoy!',
            '• Programa mañana 9am | Buenos días a todos',
            '• Muéstrame mi cola',
            '• Dame ideas de posts',
            '',
            '💬 *Engagement*',
            '• Muéstrame mis menciones',
            '• Responde a 1 con ¡Gracias por escribir!',
            '• Busca #startup',
            '',
            '⚙️ *Cuenta*',
            '• Estado · Configuración',
            '',
            'Usuarios avanzados: *shortcuts* para códigos.',
        ]);
    }

    protected static function helpFr(User $user): string
    {
        return implode("\n", [
            '👋 *XEngager sur WhatsApp*',
            '',
            'Parlez-moi naturellement — pas besoin de codes.',
            '',
            '📝 *Publier*',
            '• Poste: Lancement aujourd\'hui !',
            '• Programme demain 9h | Bonjour à tous',
            '• Montre ma file d\'attente',
            '• Donne-moi des idées de posts',
            '',
            '💬 *Engagement*',
            '• Montre mes mentions',
            '• Réponds à 1 avec Merci pour votre message !',
            '• Cherche #startup',
            '',
            '⚙️ *Compte*',
            '• Statut · Paramètres',
            '',
            'Experts : *shortcuts* pour les codes.',
        ]);
    }

    protected static function shortcutsEn(): string
    {
        return implode("\n", [
            '⚡ *Command shortcuts*',
            '',
            'post: text · schedule: time | text · thread: p1 | p2',
            'queue · ideas · draft: text · drafts',
            'mentions · reply 1: text · search: query · keywords',
            'add keyword: word · remove keyword: word',
            'analytics TWEET_ID · image: prompt · assets',
            'auto posts · auto posts 1 on · notify mentions on',
            'confirm · status · settings · lang es · unlink',
        ]);
    }

    protected static function shortcutsEs(): string
    {
        return implode("\n", [
            '⚡ *Atajos de comandos*',
            '',
            'post: texto · schedule: hora | texto · queue · mentions',
            'reply 1: texto · search: consulta · confirm · status',
        ]);
    }

    protected static function shortcutsFr(): string
    {
        return implode("\n", [
            '⚡ *Raccourcis*',
            '',
            'post: texte · schedule: heure | texte · queue · mentions',
            'reply 1: texte · search: requête · confirm · status',
        ]);
    }
}
