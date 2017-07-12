<?php

namespace Frontend\Modules\Faq\Widgets;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Common\Mailer\Message;
use Frontend\Core\Engine\Base\Widget as FrontendBaseWidget;
use Frontend\Core\Engine\Form as FrontendForm;
use Frontend\Core\Language\Language as FL;
use Frontend\Core\Engine\Model as FrontendModel;
use Frontend\Core\Engine\Navigation as FrontendNavigation;

/**
 * This is a widget with the form to ask a question
 */
class AskOwnQuestion extends FrontendBaseWidget
{
    /**
     * Form instance
     *
     * @var FrontendForm
     */
    private $frm;

    /**
     * The form status
     *
     * @var string
     */
    private $status;

    public function execute(): void
    {
        parent::execute();

        $this->loadTemplate();

        if (!$this->get('fork.settings')->get('Faq', 'allow_own_question', false)) {
            return;
        }

        $this->buildForm();
        $this->validateForm();
        $this->parse();
    }

    private function buildForm(): void
    {
        // create form
        $this->frm = new FrontendForm('own_question', '#' . FL::getAction('OwnQuestion'));
        $this->frm->addText('name')->setAttributes(['required' => null]);
        $this->frm->addText('email')->setAttributes(['required' => null, 'type' => 'email']);
        $this->frm->addTextarea('message')->setAttributes(['required' => null]);
    }

    private function parse(): void
    {
        // parse the form or a status
        if (empty($this->status)) {
            $this->frm->parse($this->tpl);
        } else {
            $this->tpl->assign($this->status, true);
        }

        // parse an option so the stuff can be shown
        $this->tpl->assign('widgetFaqOwnQuestion', true);
    }

    private function validateForm(): void
    {
        if ($this->frm->isSubmitted()) {
            $this->frm->cleanupFields();

            // validate required fields
            $this->frm->getField('name')->isFilled(FL::err('NameIsRequired'));
            $this->frm->getField('email')->isEmail(FL::err('EmailIsInvalid'));
            $this->frm->getField('message')->isFilled(FL::err('QuestionIsRequired'));

            if ($this->frm->isCorrect()) {
                $spamFilterEnabled = $this->get('fork.settings')->get('Faq', 'spamfilter');
                $variables = [];
                $variables['sentOn'] = time();
                $variables['name'] = $this->frm->getField('name')->getValue();
                $variables['email'] = $this->frm->getField('email')->getValue();
                $variables['message'] = $this->frm->getField('message')->getValue();

                if ($spamFilterEnabled) {
                    // if the comment is spam alter the comment status so it will appear in the spam queue
                    if (FrontendModel::isSpam(
                        $variables['message'],
                        SITE_URL . FrontendNavigation::getUrlForBlock('Faq'),
                        $variables['name'],
                        $variables['email']
                    )
                    ) {
                        $this->status = 'errorSpam';

                        return;
                    }
                }

                $from = $this->get('fork.settings')->get('Core', 'mailer_from');
                $to = $this->get('fork.settings')->get('Core', 'mailer_to');
                $replyTo = $this->get('fork.settings')->get('Core', 'mailer_reply_to');
                $message = Message::newInstance(sprintf(FL::getMessage('FaqOwnQuestionSubject'), $variables['name']))
                    ->setFrom([$from['email'] => $from['name']])
                    ->setTo([$to['email'] => $to['name']])
                    ->setReplyTo([$replyTo['email'] => $replyTo['name']])
                    ->parseHtml(
                        '/Faq/Layout/Templates/Mails/OwnQuestion.html.twig',
                        $variables,
                        true
                    )
                ;
                $this->get('mailer')->send($message);
                $this->status = 'success';
            }
        }
    }
}
