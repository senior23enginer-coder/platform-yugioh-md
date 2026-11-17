<?php
declare(strict_types=1);
// Minimal real columns used by the native format-roster query; SQLite only.
function ygo_worlds_test_schema(PDO $pdo): void {
    $pdo->exec('CREATE TABLE sx_ygo_decks(id INT PRIMARY KEY,name TEXT,division_id INT,seed_rank INT,active INT,team_id INT,style TEXT);
        CREATE TABLE sx_ygo_divisions(id INT,league_competition_id INT,active INT);
        CREATE TABLE sx_ygo_competitions(id INT,active INT,event_ruleset TEXT);
        CREATE TABLE sx_ygo_competition_decks(deck_id INT,competition_id INT,active INT);
        CREATE TABLE sx_ygo_matches(competition_id INT,deck_a_id INT,deck_b_id INT,deck_c_id INT,deck_d_id INT,deck_e_id INT,deck_f_id INT)');
}
function ygo_worlds_test_deck(PDO $pdo,int $id,string $name,string $format): void {
    $pdo->prepare('INSERT INTO sx_ygo_decks VALUES(?,?,NULL,?,1,1,?)')->execute([$id,$name,$id,$format]);
}
