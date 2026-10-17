<?php

return json_decode(file_get_contents(base_path('../../config/locales.json')), true, 32, JSON_THROW_ON_ERROR);
