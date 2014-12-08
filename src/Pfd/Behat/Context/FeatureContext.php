<?php

/**
 * Copyright 2014 Profideo
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Pfd\Behat\Context;

use Behat\Behat\Context\BehatContext;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Mink\Session;
use Behat\Mink\Exception\ElementNotFoundException;
use WebDriver\Key;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\UnsupportedDriverActionException;

/**
 * Useful behat feature contexts and methods
 */
class FeatureContext extends BehatContext
{
    public function __construct()
    {
        $this->useContext('mink', new MinkContext());
    }

    /**
     * Login a user
     *
     * @param string $loginLabel    Login form label
     * @param string $login         User login
     * @param string $passwordLabel Password form label
     * @param string $password      User password
     * @param string $submitLabel   Submit form label
     * @param string $page          Path of the login form
     *
     * @return array
     */
    public function login($loginLabel, $login, $passwordLabel, $password, $submitLabel, $page = '/login')
    {
        $minkContext = $this->getSubcontext('mink');

        $minkContext->visit($page);
        $minkContext->fillField($loginLabel, $login);
        $minkContext->fillField($passwordLabel, $password);
        $minkContext->pressButton($submitLabel);
    }

    /**
     * @Given /^(?:|que )j'attends quelques secondes$/
     */
    public function jAttendsQuelquesSecondes()
    {
        $this->getMinkSession()->wait(30000, '(0 === jQuery.active)');
    }

    /**
     * @Given /^(?:|que )j'attends (?P<num>\d+) secondes?$/
     */
    public function jAttendsSecondes($num)
    {
        $this->getMinkSession()->wait($num * 1000);
    }

    /**
     * @Given /^(?:|que )je sélectionne strictement "([^"]*)" depuis "([^"]*)"$/
     */
    public function jeSelectionneStrictementDepuis($arg1, $arg2)
    {
        $this->getMinkSession()->getDriver()->wait(2000, false);
        $element = $this->getVisibleField($arg2);

        if (false === $element) {
            throw new ElementNotFoundException(
            $this->getMinkSession()
            );
        }

        $option = $element->find('xpath', sprintf('//option[. = %s]', '"' . $arg1 . '"'));

        if ( !is_object($option)) {
            throw new ElementNotFoundException(
                $this->getMinkSession()
            );
        }

        $this->getMinkSession()->getDriver()->selectOption($element->getXpath(), $option->getAttribute('value'));
    }

    /**
     * @Given /^(?:|que )je clic sur l'élément "([^"]*)"$/
     */
    public function jeClicSurLElement($cssSelector)
    {
        $this->getElementWithCssSelector($cssSelector)->click();
    }

    /**
     * @Given /^(?:|que )je sélectionne "([^"]*)" depuis l\'élément "([^"]*)"$/
     */
    public function jeSelectionneDepuisLElement($option, $cssSelector)
    {
        $this->getElementWithCssSelector($cssSelector)->selectOption($option, false);
    }

    /**
     * @Given /^(?:|que )behat refuse les popups$/
     */
    public function behatRefuseLesPopups()
    {
        $this->getMinkSession()->executeScript('
            window.alert = window.confirm = function (msg) {
                document.getElementById("__alert_container__").innerHTML = msg;

                return false;
            };
            var div = document.createElement("div");
            div.id = "__alert_container__";
            document.body.appendChild(div);
        ');
    }

    /**
     * @Given /^(?:|que )behat accepte les popups$/
     */
    public function behatAccepteLesPopups()
    {
        $this->getMinkSession()->executeScript('
            window.alert = window.confirm = function (msg) {
                document.getElementById("__alert_container__").innerHTML = msg;

                return true;
            };
            var div = document.createElement("div");
            div.id = "__alert_container__";
            document.body.appendChild(div);
        ');
    }

    /**
     * @Then /^(?:|je )devrais voir "([^"]*)" dans la popup$/
     *
     * @param string $message The message.
     *
     * @return bool
     */
    public function jeDevraisVoirDansLaPopup($message)
    {
        $element = $this->getMinkSession()->getPage()->findById('__alert_container__');

        return $message == $element->getHtml();
    }

    /**
     * @When /^je survole "([^"]*)"$/
     */
    public function jeSurvole($selector)
    {
        $element = $this->getMinkSession()->getPage()->find('css', $selector);

        if (null === $element) {
            throw new \InvalidArgumentException(sprintf('Could not evaluate CSS selector: "%s"', $selector));
        }

        $element->mouseOver();
    }

    /**
     * @Then /^(?:|que )l\'option "([^"]*)" devrait être sélectionnée dans "([^"]*)"$/
     */
    public function lOptionDevraitEtreSelectionneeDans($optionValue, $cssSelector)
    {
        $selectElement = $this->getElementWithCssSelector($cssSelector);
        $optionElement = $selectElement->find('named', array('option', "\"{$optionValue}\""));

        \PHPUnit_Framework_Assert::assertTrue($optionElement->hasAttribute("selected"));
    }

    /**
     * @Then /^(?:|que )je devrais voir l\'élément correspondant au xpath "([^"]*)"$/
     */
    public function jeDevraisVoirLElementCorrespondantAuXpath($element)
    {
        $this->getSubcontext('mink')->assertSession()->elementExists('xpath', $element);
    }

    /**
     * @Then /^(?:|que )je devrais voir (\d+) éléments? correspondant au xpath "([^"]*)"$/
     */
    public function jeDevraisVoirElementsCorrespondantAuXpath($num, $element)
    {
        $this->getSubcontext('mink')->assertSession()->elementsCount('xpath', $element, intval($num));
    }

    /**
     * @When /^je vide le champ "([^"]*)"$/
     */
    public function jeVideLeChamp($field)
    {
        $this->getSubcontext('mink')->fillField($field, Key::BACKSPACE);
    }

    protected function getElementWithCssSelector($cssSelector)
    {
        $session = $this->getMinkSession();
        $element = $session->getPage()->find(
            'xpath',
            $session->getSelectorsHandler()->selectorToXpath('css', $cssSelector)
        );
        if (null === $element) {
            throw new \InvalidArgumentException(sprintf('L\'élement "%s" n\'existe pas, ou n\'est pas visible', $cssSelector));
        }

        return $element;
    }

    /**
     * return a visible element Field
     *
     * @param string $locator button id, value or alt
     *
     * @return NodeElement
     */
    protected function getVisibleField($locator)
    {
        $page = $this->getMinkSession()->getPage();
        $elements = $page->findAll('named', array(
            'field', $page->getSession()->getSelectorsHandler()->xpathLiteral($locator)
        ));
        $element  = false;

        foreach ($elements as $elt) {
            if ($elt->isVisible()) {
                $element = $elt;
                break;
            }
        }

        return $element;
    }

    /**
     * @return Session
     */
    public function getMinkSession()
    {
        return $this->getSubcontext('mink')->getSession();
    }

    /**
     * @Then /^(?:|que )l\'élément "([^"]*)" a la propriété "([^"]*)" avec la valeur "([^"]*)"$/
     */
    public function lElementALaProprieteAvecLaValeur($element, $property, $value)
    {
        $element = $this->getElementWithCssSelector($element);
        $nodeElement = new NodeElement($element->getXpath(), $this->getMinkSession());

        return $nodeElement->getAttribute($property) === $value;
    }

    /**
     * @Then /^(?:|que )l\'élément "([^"]*)" n'a pas la propriété "([^"]*)" avec la valeur "([^"]*)"$/
     */
    public function lElementNAPasLaProprieteAvecLaValeur($element, $property, $value)
    {
        $element = $this->getElementWithCssSelector($element);
        $nodeElement = new NodeElement($element->getXpath(), $this->getMinkSession());

        return (!$nodeElement->hasAttribute($property)) || ($nodeElement->getAttribute($property) !== $value);
    }

    /**
     * @When /^je vais sur la fenêtre "([^"]*)"$/
     */
    public function jeVaisSurLaFenetre($name)
    {
        $this->getMinkSession()->switchToWindow($name);
    }

    /**
     * @Then /^l'élément "([^"]*)" devrait contenir la date et l'heure courante avec une approximation de ([^"]*) secondes$/
     */
    public function lElementDevraitContenirLaDateCouranteAvecUneApproximationDeSecondes($cssSelector, $approximation)
    {
        $element = $this->getElementWithCssSelector($cssSelector);
        $pattern = '/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}/';

        if (!preg_match($pattern, $element->getHtml(), $matches)) {
            throw new \PHPUnit_Framework_ExpectationFailedException(
                sprintf("Le motif de date %s n'a pas été trouvé dans l'élément %s", $pattern, $cssSelector)
            );
        }
        if (time() - \DateTime::createFromFormat('d/m/Y H:i:s', $matches[0])->getTimestamp() > $approximation ) {
            throw new \PHPUnit_Framework_ExpectationFailedException(
                sprintf("La date %s se trouvant dans l'élément %s est en dehors de l'approximation de(s) %d seconde(s)",
                    $matches[0],
                    $cssSelector,
                    $approximation
                )
            );
        }
    }

    /**
     * @Then /^l'élément "([^"]*)" devrait contenir la date courante$/
     */
    public function lElementDevraitContenirLaDateCourante($cssSelector)
    {
        $element = $this->getElementWithCssSelector($cssSelector);
        $pattern = '/\d{2}\/\d{2}\/\d{4}/';

        if (!preg_match('/\d{2}\/\d{2}\/\d{4}/', $element->getHtml(), $matches)) {
            throw new \PHPUnit_Framework_ExpectationFailedException(
                sprintf("Le motif de date %s n'a pas été trouvé dans l'élément", $pattern, $cssSelector)
            );
        }

        \PHPUnit_Framework_Assert::assertEquals(date('d/m/Y'), $matches[0]);
    }

    /**
     * Return a radio button
     *
     * @param type $label
     *
     * @return NodeElement
     *
     * @throws ElementNotFoundException
     */
    private function getRadioButton($label)
    {
        $radioButton = $this->getMinkSession()->getPage()->findField($label);

        if (null === $radioButton) {
            throw new ElementNotFoundException($this->getMinkSession(), 'form field', 'id|name|label|value', $label);
        }

        return $radioButton;
    }

    /**
     * @param string $label
     *
     * @throws ElementNotFoundException
     *
     * @return void
     *
     * @Given /^je sélectionne le bouton radio "([^"]*)"$/
     */
    public function jeSelectionneLeBoutonRadio($label)
    {
        $radioButton = $this->getRadioButton($label);

        $this->getMinkSession()->getDriver()->click($radioButton->getXPath());
    }

    /**
     * Select radio button with specified id|title|alt|text
     *
     * @param string $label
     *
     * @throws ElementNotFoundException
     *
     * @return boolean
     *
     * @Then /^le bouton radio "([^"]*)" est sélectionné$/
     */
    public function leBoutonRadioEstSelectionne($label)
    {
        $radioButton = $this->getRadioButton($label);

        return $radioButton->hasAttribute('checked');
    }

    /**
     * @Then /^le bouton "([^"]*)" doit être désactivé$/
     *
     */
    public function leBoutonDoitEtreDesactive($locator)
    {
        \PHPUnit_Framework_Assert::assertTrue($this->elementIsDisabled('button', $locator));
    }

    /**
     * @Then /^le bouton "([^"]*)" doit être activé$/
     *
     */
    public function leBoutonDoitEtreActive($locator)
    {
        \PHPUnit_Framework_Assert::assertFalse($this->elementIsDisabled('button', $locator));
    }

    /**
     * @Then /^le lien "([^"]*)" doit être désactivé$/
     *
     */
    public function leLienDoitEtreDesactive($locator)
    {
        \PHPUnit_Framework_Assert::assertTrue($this->elementIsDisabled('link', $locator));
    }

    /**
     * @Then /^le lien "([^"]*)" doit être activé$/
     *
     */
    public function leLienDoitEtreActive($locator)
    {
        \PHPUnit_Framework_Assert::assertFalse($this->elementIsDisabled('link', $locator));
    }

    /**
     * Check if an element has the disabled attribute
     *
     * @param string $type    Element type
     * @param string $locator Locator
     *
     * @return boolean
     *
     * @throws \Behat\Mink\Exception\ElementNotFoundException When element is not found
     */
    protected function elementIsDisabled($type, $locator)
    {
        $session = $this->getMinkSession();
        $element = $session->getPage()->find('named', array(
            $type, $session->getSelectorsHandler()->xpathLiteral($locator)
        ));

        if (null === $element) {
            throw new ElementNotFoundException(
                    $session, 'element', 'css', $locator
            );
        }

        return $element->hasAttribute('disabled');
    }

    /**
     * Checks if current mink driver used supports javascript
     *
     * @return boolean
     */
    public function driverSupportsJs()
    {
        try {
            $this->getMinkSession()->getDriver()->evaluateScript('');
        } catch (UnsupportedDriverActionException $exception) {
            return false;
        }

        return true;
    }
}
