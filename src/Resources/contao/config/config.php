<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

$GLOBALS['BE_MOD']['content']['survey'] = [
    'tables' => ['tl_survey', 'tl_survey_question', 'tl_survey_question_dependency'],
];

$GLOBALS['BE_FFL']['range'] = \Mvo\ContaoSurvey\Widget\RangeWidget::class;
