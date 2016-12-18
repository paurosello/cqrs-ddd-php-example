<?php

namespace CodelyTv\Api\Test\Behat\ApiContext;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\MinkExtension\Context\RawMinkContext;
use CodelyTv\Api\Test\Mink\MinkHelper;
use CodelyTv\Api\Test\Mink\MinkSessionResponseHelper;
use CodelyTv\Test\PhpUnit\Constraint\CodelyTvConstraintIsSimilar;
use DateTimeImmutable;
use Exception;
use PHPUnit_Framework_Assert;
use function CodelyTv\Utils\date_to_string;

class ApiResponseContext extends RawMinkContext implements Context
{
    /** @var MinkSessionResponseHelper */
    private $sessionResponseHelper;
    /** @return MinkHelper */
    private $sessionHelper;

    public static function assertJsonStringEqualsJsonString($expectedJson, $actualJson, $message = '')
    {
        PHPUnit_Framework_Assert::assertJson($expectedJson, 'The expected value is not a valid json');
        PHPUnit_Framework_Assert::assertJson($actualJson, 'The actual value is not a valid json');

        $expected = json_decode($expectedJson);
        $actual   = json_decode($actualJson);

        PHPUnit_Framework_Assert::assertThat(
            $actual,
            new CodelyTvConstraintIsSimilar($expected, 20), // @todo For functional
            $message
        );
    }

    /**
     * @Then the response content should be:
     */
    public function theResponseContentShouldBe(PyStringNode $expected)
    {
        if ($this->getSessionHelper()->getResponseHeader('content-type') === 'application/json') {
            $this->assertJsonStringEqualsJsonString(
                $this->adaptExpected($expected->getRaw()),
                $this->getSessionResponseHelper()->getResponse(),
                sprintf('The string "%s" is not equal to the response of the current page', $expected)
            );
        } else {
            PHPUnit_Framework_Assert::assertEquals(
                $expected->getRaw(),
                $this->getSessionResponseHelper()->getResponse(),
                sprintf('The string "%s" is not equal to the response of the current page', $expected)
            );
        }
    }

    /**
     * @Then the response should be empty
     */
    public function theResponseShouldBeEmpty()
    {
        PHPUnit_Framework_Assert::assertEmpty(
            $this->getSessionResponseHelper()->getResponse(),
            'The response of the current page is not empty'
        );
    }

    /**
     * @Then print last api response
     */
    public function printApiResponse()
    {
        print_r($this->getSessionResponseHelper()->getResponse());
    }

    /**
     * @Then the response parameter :name should exist
     */
    public function theResponseParameterShouldExist($name)
    {
        if (!$this->getSessionHelper()->hasResponseParameter($name)) {
            throw new Exception(sprintf('Parameter "%s" does not exists in response', $name));
        }
    }

    /**
     * @Then the response parameter :name should match :regex
     */
    public function theResponseParameterShouldMatch($name, $regex)
    {
        $value        = $this->getSessionHelper()->getResponseParameter($name);
        $errorMessage = vsprintf(
            'The response parameter "%s" is "%s" and it should match "%s" but it does not.',
            [$name, $value, $regex]
        );

        PHPUnit_Framework_Assert::assertRegExp($regex, (string) $value, $errorMessage);
    }

    /**
     * @Then the response parameter :name should be :expectedValue
     */
    public function theResponseParameterShouldBe($name, $expectedValue)
    {
        $value = $this->getSessionHelper()->getResponseParameter($name);
        PHPUnit_Framework_Assert::assertEquals($expectedValue, $value);
    }

    /**
     * @Then print response headers
     */
    public function printResponseHeaders()
    {
        print_r($this->getSessionHelper()->getResponseHeaders());
    }

    /**
     * @Then the response header :name should be :value
     */
    public function theResponseHeaderShouldBe($name, $value)
    {
        $this->theHeaderShouldExists($name);

        $header = $this->getSessionHelper()->getResponseHeader($name);

        PHPUnit_Framework_Assert::assertSame(
            $value,
            $header,
            sprintf('The header "%s" is equal to "%s"', $name, $header)
        );
    }

    /**
     * @Then the response header :name should exists
     */
    public function theHeaderShouldExists($name)
    {
        if (!$this->getSessionHelper()->hasResponseHeader($name)) {
            throw new Exception(sprintf('The header "%s" does not exists', $name));
        }
    }

    /**
     * @Then the response status code should be :expectedResponseCode
     */
    public function theResponseStatusCodeShouldBe($expectedResponseCode)
    {
        PHPUnit_Framework_Assert::assertSame((int) $expectedResponseCode, $this->getSession()->getStatusCode());
    }

    private function getSessionResponseHelper()
    {
        return $this->sessionResponseHelper = $this->sessionResponseHelper ?: new MinkSessionResponseHelper(
            $this->getSessionHelper()
        );
    }

    private function getSessionHelper()
    {
        return $this->sessionHelper = $this->sessionHelper ?: new MinkHelper($this->getSession());
    }

    private function adaptExpected($expectedResponse)
    {
        return $this->convertRelativeDates($expectedResponse);
    }

    private function convertRelativeDates($expectedResponse)
    {
        if (preg_match_all('/\#date (?<dates>[^\#]+)\#/', $expectedResponse, $matches)) {
            foreach ($matches['dates'] as $date) {
                $expectedResponse = str_replace(
                    sprintf('#date %s#', $date),
                    date_to_string(new DateTimeImmutable($date)),
                    $expectedResponse
                );
            }
        }

        return $expectedResponse;
    }
}