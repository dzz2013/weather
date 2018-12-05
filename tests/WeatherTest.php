<?php

namespace Xkeyi\Weather\Tests;

use Xkeyi\Weather\Weather;
use Xkeyi\Weather\Exceptions\HttpException;
use Xkeyi\Weather\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Mockery\Matcher\AnyArgs;

class WeatherTest extends TestCase
{
    public function testGetWeather()
    {
        // json
        $response = new Response(200, [], '{"success": true}');
        $client = \Mockery::mock(Client::class);
        $client->allows()->get('https://restapi.amap.com/v3/weather/weatherInfo', [
            'query' => [
                'key' => 'mock-key',
                'city' => '南京',
                'output' => 'json',
                'extensions' => 'base',
            ],
        ])->andReturn($response);

        $weather = \Mockery::mock(Weather::class, ['mock-key'])->makePartial();
        $weather->allows()->getHttpClient()->andReturn($client);

        $this->assertSame(['success' => true], $weather->getWeather('南京'));

        // xml
        $response = new Response(200, [], '<hello>content</hello>');
        $client = \Mockery::mock(Client::class);
        $client->allows()->get('https://restapi.amap.com/v3/weather/weatherInfo', [
            'query' => [
                'key' => 'mock-key',
                'city' => '南京',
                'extensions' => 'all',
                'output' => 'xml',
            ],
        ])->andReturn($response);

        $weather = \Mockery::mock(Weather::class, ['mock-key'])->makePartial();
        $weather->allows()->getHttpClient()->andReturn($client);

        $this->assertSame('<hello>content</hello>', $weather->getWeather('南京', 'all', 'xml'));
    }

    public function testGetWeatherWithGuzzleRuntimeException()
    {
        $client = \Mockery::mock(Client::class);
        $client->allows()
            ->get(new AnyArgs()) // 由于上面的用例已经验证过参数传递，所以这里就不关心参数了。
            ->andThrow(new \Exception('request timeout')); // 当调用 get 方法时会抛出异常。

        $weather = \Mockery::mock(Weather::class, ['mock-key'])->makePartial();
        $weather->allows()->getHttpClient()->andReturn($client);

        // 接着需要断言调用时会产生异常。
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('request timeout');

        $weather->getWeather('南京');
    }

    // 检查 $type 参数
    public function testGetWeatherWithInvalidType()
    {
        $weather = new Weather('mock-key');

        // 断言会抛出此异常类
        $this->expectException(InvalidArgumentException::class);
        // 断言异常消息为
        $this->expectExceptionMessage('Invalid type value(base/all):foo');

        $weather->getWeather('南京', 'foo');

        $this->fail('Failed to assert getWeather throw exception with invalid argument.');
    }

    // 检查 $format 参数
    public function testGetWeatherWithInvalidFormat()
    {
        $weather = new Weather('mock-key');

        // 断言会抛出此异常类
        $this->expectException(InvalidArgumentException::class);

        // 断言异常消息为
        $this->expectExceptionMessage('Invalid response format:array');

        // 因为支持的格式为 xml/json，所以传入 array 会抛出异常
        $weather->getWeather('南京', 'base', 'array');

        // 如果没有抛出异常，就会运行到这行，标记当前测试没成功
        $this->fail('Failed to assert getWeather throw exception with invalid argument.');
    }

    public function testGetHttpClient()
    {
        $weather = new Weather('mock-key');

        // 断言返回结果为 GuzzleHttp\ClientInterface 实例
        $this->assertInstanceOf(ClientInterface::class, $weather->getHttpClient());
    }

    public function testSetGuzzleOptions()
    {
        $weather = new Weather('mock-key');

        // 设置参数前，timeout 为 null
        $this->assertNull($weather->getHttpClient()->getConfig('timeout'));

        // 设置参数
        $weather->setGuzzleOptions(['timeout' => 5000]);

        // 设置参数后，timeout 为 5000
        $this->assertSame(5000, $weather->getHttpClient()->getConfig('timeout'));
    }
}
