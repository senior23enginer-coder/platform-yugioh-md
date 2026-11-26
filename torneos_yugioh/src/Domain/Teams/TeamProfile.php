<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Teams;

final class TeamProfile
{
    public static function forDeck(string $deckName, ?string $archetype = null): array
    {
        $base = trim((string) ($archetype ?: $deckName));
        $base = preg_replace('/\s+/', ' ', $base) ?: 'Duel';
        $normalized = strtolower($base);
        $profiles = [
            'kewl tune' => ['Neon Pulse Syndicate', 'NRS', '#21f6ff', '#ffde59', 'NR'],
            'branded' => ['Crimson Exiles', 'CAL', '#dc2626', '#f7d154', 'CA'],
            'vanquish soul k9' => ['Soulhound Vanguard', 'SHK', '#2563eb', '#f97316', 'K9'],
            'mitsurugi pure' => ['Crimson Shogunate', 'MSG', '#ef4444', '#111827', 'MS'],
            'elfnote' => ['Ethereal Symphony', 'ENS', '#22c55e', '#f0abfc', 'ES'],
            'chaos ritual' => ['Abyssal Ascendants', 'CHA', '#7c3aed', '#facc15', 'CH'],
            'dracotail' => ['Drakecourt Dominion', 'DTD', '#0891b2', '#f59e0b', 'DD'],
            'doomz' => ['Graveborn Revenants', 'DZR', '#4b5563', '#a855f7', 'DZ'],
            'ryzeal' => ['Nova Reactor', 'RZO', '#facc15', '#2563eb', 'RZ'],
            'maliss' => ['Darknet Protocol', 'MLP', '#ec4899', '#111827', 'MP'],
            'tearlaments' => ['Abyssal Elegy', 'TAB', '#0ea5e9', '#312e81', 'TA'],
            'snake-eyes' => ['Crimson-Eyed Serpents', 'SCE', '#ef4444', '#f97316', 'SE'],
            'fire king' => ['Inferno Crown', 'IFK', '#f97316', '#fde047', 'IK'],
            'unchained' => ['Broken Chain Legion', 'UCD', '#991b1b', '#64748b', 'UD'],
            'purrely' => ['Celestial Companions', 'PNV', '#f9a8d4', '#38bdf8', 'PN'],
            'swordsoul' => ['Celestial Blades', 'SSD', '#60a5fa', '#e5e7eb', 'SD'],
            'sky striker' => ['Aerial Valkyries', 'SSV', '#3b82f6', '#ef4444', 'SV'],
            'doomz artmage' => ['Arcane Deathcallers', 'DZA', '#9333ea', '#f59e0b', 'DA'],
            'white forest azamina' => ['Frostveil Covenant', 'AFW', '#e0f2fe', '#22c55e', 'AF'],
            'kashtira pure' => ['Astral Conquerors', 'KSC', '#dc2626', '#111827', 'KC'],
            'demonherrero bystial' => ['Infernal Forgelords', 'BBS', '#f97316', '#1f2937', 'BB'],
            'cyber dragon k9' => ['Chrome Hound Unit', 'CHK', '#06b6d4', '#d1d5db', 'CK'],
            'invoked' => ['Arcane Summoners', 'IVA', '#8b5cf6', '#f8fafc', 'IA'],
            'mathmech' => ['Binary Singularity', 'MTS', '#14b8a6', '#facc15', 'MS'],
            'infernoble' => ['Flamebound Paladins', 'INO', '#dc2626', '#fbbf24', 'IO'],
            'memento' => ['Deathbound Archive', 'MMR', '#64748b', '#c084fc', 'MM'],
            'exosister' => ['Sacred Exorcist Order', 'EXC', '#f472b6', '#ffffff', 'EC'],
            'salamangreat' => ['Solar Inferno', 'SGI', '#ef4444', '#f97316', 'SI'],
            'k9 pure' => ['Blackwatch Unit', 'KBO', '#111827', '#38bdf8', 'K9'],
            'kashtira demonherrero' => ['Voidforge Legion', 'KFM', '#b91c1c', '#f97316', 'KF'],
            'resonator rda horus' => ['Crimson Sun Tyrants', 'HCR', '#b91c1c', '#facc15', 'HR'],
            'crystron' => ['Quantum Sentinels', 'CRN', '#38bdf8', '#94a3b8', 'CN'],
            'vanquish soul zoodiac' => ['Zodiac Brawlers', 'VZC', '#ef4444', '#22c55e', 'VZ'],
            'exodia' => ['Fivefold Ascension', 'FBO', '#d97706', '#fef3c7', 'FO'],
            'blue dragon' => ['Azure Sovereigns', 'ADL', '#2563eb', '#bfdbfe', 'AD'],
            'dark world' => ['Underworld Renegades', 'DWO', '#1f2937', '#a855f7', 'DW'],
            'resonator red dragon archfiend' => ['Scarlet Tyrants', 'RDD', '#dc2626', '#111827', 'RD'],
            'traptrix ragnaraika' => ['Thornveil Brood', 'TVS', '#16a34a', '#f472b6', 'TS'],
            'cyber dragon pure' => ['Ironfang Rebellion', 'CDA', '#06b6d4', '#e5e7eb', 'CD'],
            'six samurai' => ['Last Shogun Clan', 'SSC', '#dc2626', '#facc15', 'SC'],
            'barrera de hielo' => ['Glacial Wardens', 'FRB', '#38bdf8', '#ffffff', 'FB'],
            'volcanic' => ['Ashen Cataclysm', 'VLE', '#ef4444', '#f97316', 'VE'],
            'horus' => ['Sunborn Dynasty', 'HSE', '#f59e0b', '#fde68a', 'HS'],
        ];
        $profile = $profiles[$normalized] ?? [$base . ' Club', strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $base), 0, 3)) ?: 'YMD', '#36d8ff', '#ffd25f', strtoupper(substr($base, 0, 2))];
        return [
            'name' => $profile[0],
            'code' => $profile[1],
            'primary' => $profile[2],
            'secondary' => $profile[3],
            'symbol' => $profile[4],
        ];
    }
}
