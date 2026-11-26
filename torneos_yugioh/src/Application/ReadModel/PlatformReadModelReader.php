<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\ReadModel;

/** Compatibility aggregate for the request-level loader; feature consumers use narrow ports. */
interface PlatformReadModelReader extends
    SportingReadModelReader,
    DeckIdentityReadModelReader,
    HistoricalReadModelReader,
    RulesReadModelReader {}
