<?php

function getAnimalsAndPlantsFound(): array
{
    return [
        'animals' => ['lion', 'elephant', 'dolphin', 'eagle'],
        'plants' => ['oak', 'fern', 'rose', 'cactus'],
    ];
}

$foundLife = getAnimalsAndPlantsFound();

header('Content-Type: application/json');
echo json_encode($foundLife, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
