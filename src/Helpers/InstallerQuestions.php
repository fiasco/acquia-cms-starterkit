<?php

namespace AcquiaCMS\Cli\Helpers;

use AcquiaCMS\Cli\Helpers\Parsers\PHPParser;

/**
 * A class for adding any utility functions.
 */
class InstallerQuestions {

  /**
   * Gets All questions based on user selected user-case.
   *
   * @param array $questions
   *   An array of all questions.
   * @param string $bundle
   *   A name of the user selected use-case.
   *
   * @return array
   *   Returns the questions for the user selected use-case.
   */
  public function getQuestions(array $questions, string $bundle) :array {
    $questionMustAsk = $questionCanAsk = $questionSkipped = [];
    foreach ($questions as $key => $question) {
      if ($this->filterBySiteTemplate($question, $bundle)) {
        $questionMustAsk[$key] = $question;
      }
      elseif ($this->filterByQuestion($question)) {
        $questionCanAsk[$key] = $question;
      }
      else {
        // As we are not asking this question, so set `skip_on_value` to TRUE.
        // This is done, because when function process() is called,
        // it'll add this question in default value question and
        // this question won't be asked to the user.
        $question['skip_on_value'] = TRUE;
        $questionSkipped[$key] = $question;
      }
    }
    return [
      'questionMustAsk' => $questionMustAsk,
      'questionCanAsk' => $questionCanAsk,
      'questionSkipped' => $questionSkipped,
    ];
  }

  /**
   * Filter the questions based on user selected use-case.
   *
   * @param array $question
   *   A Question array.
   * @param string $bundle
   *   A name of the user selected use-case.
   *
   * @return bool
   *   Returns true|false, if question needs to ask.
   */
  public function filterBySiteTemplate(array $question, string $bundle) :bool {
    $isValid = TRUE;
    if (isset($question['dependencies']['site_templates'])) {
      $siteTemplates = array_map('trim', explode('||', $question['dependencies']['site_templates']));
      if (!in_array($bundle, $siteTemplates)) {
        $isValid = FALSE;
      }
    }
    return $isValid;
  }

  /**
   * Filter the questions based on other dependent question.
   *
   * @param array $question
   *   An Array of question.
   *
   * @return bool
   *   Returns true|false, if question needs to ask.
   */
  public function filterByQuestion(array $question) :bool {
    $isValid = FALSE;
    // Here, we are just filtering to check if we should ask question or not.
    // At this point, we don't know what answer user would give.
    // Based on user answer, we'll decide, if we should ask question.
    // @see shouldAskQuestion().
    if (isset($question['dependencies']['questions'])) {
      $isValid = TRUE;
    }
    return $isValid;
  }

  /**
   * Process all the questions.
   *
   * @param array $questions
   *   An array of filtered questions.
   *
   * @return array
   *   Returns an array of default values for questions and questions to ask.
   */
  public function process(array $questions) :array {
    $defaultValues = $questionToAsk = [];
    foreach ($questions as $key => $question) {
      $defaultValue = $this->getDefaultValue($question, $key);
      $isSkip = $question['skip_on_value'] ?? TRUE;
      if (!$defaultValue || !$isSkip) {
        $questionToAsk[$key] = $question;
      }
      else {
        $defaultValues[$key] = $defaultValue;
      }
    }
    return [
      'default' => $defaultValues,
      'questionToAsk' => $questionToAsk,
    ];
  }

  /**
   * Returns the default value for the question.
   *
   * @param array $question
   *   An array of question.
   * @param string $key
   *   A unique question key.
   *
   * @return string
   *   Returns the default value for question.
   */
  public function getDefaultValue(array $question, string $key = ""): string {
    $defaultValue = '';
    if ($key) {
      $defaultValue = getenv($key);
    }
    $defaultValue = $question['default_value'] ?? $defaultValue;
    return trim(PHPParser::parseEnvVars($defaultValue));
  }

  /**
   * Determines if question should be asked.
   *
   * @param array $question
   *   An array of question.
   * @param array $userInputValues
   *   An array of user answer for question.
   *
   * @return bool
   *   Returns true|false, if question should be asked.
   */
  public function shouldAskQuestion(array $question, array $userInputValues): bool {
    $questionsExpressionArray = $question['dependencies']['questions'];
    $isValid = FALSE;
    foreach ($questionsExpressionArray as $questionsExpression) {
      $questionsExpression = array_map('trim', explode('||', $questionsExpression));
      $isValid = FALSE;
      foreach ($questionsExpression as $questionExpression) {
        $questionMatches = PHPParser::parseQuestionExpression($questionExpression);
        $conditionKey = $questionMatches[1] ?? '';
        if ($conditionKey && isset($userInputValues[$conditionKey])) {
          $questionValue = trim($questionMatches[5], '"');
          switch ($questionMatches[3]) {
            case "==":
              $isValid = $userInputValues[$conditionKey] == $questionValue;
              break;

            case "!=":
              $isValid = $userInputValues[$conditionKey] != $questionValue;
              break;

            case ">":
              $isValid = $userInputValues[$conditionKey] > $questionValue;
              break;

            case ">=":
              $isValid = $userInputValues[$conditionKey] >= $questionValue;
              break;

            case "<":
              $isValid = $userInputValues[$conditionKey] < $questionValue;
              break;

            case "<=":
              $isValid = $userInputValues[$conditionKey] <= $questionValue;
              break;

            default:
              throw new \RuntimeException("Invalid condition or condition not defined: " . $questionMatches[3]);
          }
        }
        else {
          throw new \RuntimeException('Not able to resolve variable: ${' . $conditionKey . '} for expression: ' . $questionExpression);
        }
        if ($isValid) {
          break;
        }
      }
      if (!$isValid) {
        break;
      }
    }
    return $isValid;
  }

}
