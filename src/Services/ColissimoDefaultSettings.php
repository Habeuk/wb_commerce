<?php

namespace Drupal\wb_commerce\Services;

use Drupal\hbkcolissimochrono\Services\ColissimoDefaultSettings as ServicesColissimoDefaultSettings;
use Stephane888\Debug\Repositories\ConfigDrupal;

/**
 * Surcharge du service  hbkcolissimochrono.default_settings
 */
class ColissimoDefaultSettings extends ServicesColissimoDefaultSettings {


  public function getSettings() {
    return ConfigDrupal::config("hbkcolissimochrono.settings");
  }
}
