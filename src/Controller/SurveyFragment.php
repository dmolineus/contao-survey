<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoSurvey\Controller;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\Template;
use Doctrine\ORM\EntityManager;
use Mvo\ContaoSurvey\Entity\Record;
use Mvo\ContaoSurvey\Entity\Survey;
use Mvo\ContaoSurvey\Form\SurveyManager;
use Mvo\ContaoSurvey\Form\SurveyManagerFactory;
use Mvo\ContaoSurvey\Repository\SurveyRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

/**
 * @ContentElement(category="includes")
 */
class SurveyFragment extends AbstractContentElementController
{
    private SurveyRepository $surveyRepository;
    private SurveyManagerFactory $managerFactory;
    private ScopeMatcher $scopeMatcher;
    private EntityManager $entityManager;
    private CsrfTokenManager $csrfTokenManager;
    private string $csrfTokenName;

    public function __construct(SurveyRepository $surveyRepository, SurveyManagerFactory $managerFactory, ScopeMatcher $scopeMatcher, EntityManager $entityManager, CsrfTokenManager $csrfTokenManager, string $csrfTokenName)
    {
        $this->surveyRepository = $surveyRepository;
        $this->managerFactory = $managerFactory;
        $this->scopeMatcher = $scopeMatcher;
        $this->entityManager = $entityManager;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->csrfTokenName = $csrfTokenName;
    }

    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        // load form helper asset
        $GLOBALS['TL_JAVASCRIPT']['like_widget'] = 'bundles/mvocontaosurvey/survey_frontend.js';

        /** @var Survey|null $survey */
        $survey = $this->surveyRepository->find((int) $model->survey);

        if (null === $survey) {
            // return empty response if survey wasn't found
            return new Response();
        }

        $headline = $model->survey_headline ?: $survey->getTitle();

        if ($this->scopeMatcher->isBackendRequest($request)) {
            return $this->render('@MvoContaoSurvey/Backend/survey_content.html.twig', [
                'headline' => $headline,
                'survey' => $survey,
            ]);
        }

        $manager = $this->managerFactory->__invoke($survey);
        $manager->form->handleRequest($request);

        if ($this->proceedUntilCompleted($manager)) {
            $this->storeRecord($survey, $manager->getAnswers());
            $manager->reset();

            return $this->render('@MvoContaoSurvey/_thanks.html.twig', [
                'headline' => $headline,
                'survey' => $survey,
                'class' => 'survey survey--thanks',
            ]);
        }

        $currentQuestion = $manager->getCurrentQuestion();
        $currentStep = $manager->getCurrentStep(false);

        return $this->render('@MvoContaoSurvey/_step.html.twig', [
            // survey
            'headline' => $headline,
            'survey' => $survey,
            'total_steps' => $manager->getTotalSteps(),
            'class' => sprintf('survey survey--id_%d survey--step_%d', $survey->getId(), $currentStep),

            // current step
            'current_step' => [
                'index' => $currentStep,
                'is_first' => $manager->isFirstStep(),
                'is_last' => $manager->isLastStep(),
                'type' => $manager->getCurrentType(),
                'form' => $manager->form->createView(),
                'question' => $currentQuestion,
            ],

            // we cannot disable Contao's CSRF token protection for fragments, so we just pass the token
            'contao_csrf_token' => $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue(),
        ]);
    }

    private function proceedUntilCompleted(SurveyManager $manager): bool
    {
        // form wasn't even submitted
        if (!$manager->form->isSubmitted()) {
            return false;
        }

        // reset (back to the first step)
        if ($manager->form['reset']->isClicked()) {
            $manager->reset();

            return false;
        }

        // back to the previous step
        if (isset($manager->form['previous']) && $manager->form['previous']->isClicked() && $manager->previousStep()) {
            return false;
        }

        // go to next step OR we're finally done
        if ($manager->form->isValid()) {
            $manager->saveCurrentStep();

            return !$manager->nextStep();
        }

        // form contains errors
        return false;
    }

    private function storeRecord(Survey $survey, array $answers): void
    {
        $record = new Record($survey, $answers);

        // todo validate

        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }
}
