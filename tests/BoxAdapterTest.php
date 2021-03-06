<?php

namespace TappNetwork\FlysystemBox\Test;

use Prophecy\Argument;
use TappNetwork\Box\Client;
use League\Flysystem\Config;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TappNetwork\Box\Exceptions\BadRequest;
use TappNetwork\FlysystemBox\BoxAdapter;

class BoxAdapterTest extends TestCase
{
    /** @var \TappNetwork\Box\Client|\Prophecy\Prophecy\ObjectProphecy */
    protected $client;

    /** @var \TappNetwork\FlysystemBox\BoxAdapter */
    protected $boxAdapter;

    public function setUp()
    {
        $this->client = $this->prophesize(Client::class);

        $this->boxAdapter = new BoxAdapter($this->client->reveal(), 'prefix');
    }

    /** @test */
    public function it_can_write()
    {
        $this->client->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $result = $this->boxAdapter->write('something', 'contents', new Config());

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    /** @test */
    public function it_can_update()
    {
        $this->client->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $result = $this->boxAdapter->update('something', 'contents', new Config());

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    /** @test */
    public function it_can_write_a_stream()
    {
        $this->client->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $result = $this->boxAdapter->writeStream('something', tmpfile(), new Config());

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    /** @test */
    public function it_can_upload_using_a_stream()
    {
        $this->client->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $result = $this->boxAdapter->updateStream('something', tmpfile(), new Config());

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    /**
     * @test
     *
     * @dataProvider  metadataProvider
     */
    public function it_has_calls_to_get_meta_data($method)
    {
        $this->client = $this->prophesize(Client::class);
        $this->client->getMetadata('/one')->willReturn([
            '.tag'   => 'file',
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/one',
        ]);

        $this->boxAdapter = new BoxAdapter($this->client->reveal());

        $this->assertInternalType('array', $this->boxAdapter->{$method}('one'));
    }

    public function metadataProvider(): array
    {
        return [
            ['getMetadata'],
            ['getTimestamp'],
            ['getSize'],
            ['has'],
        ];
    }

    /** @test */
    public function it_will_not_hold_metadata_after_failing()
    {
        $this->client = $this->prophesize(Client::class);

        $this->client->getMetadata('/one')->willThrow(new BadRequest(new Response(409)));

        $this->boxAdapter = new BoxAdapter($this->client->reveal());

        $this->assertFalse($this->boxAdapter->has('one'));
    }

    /** @test */
    public function it_can_read()
    {
        $stream = tmpfile();
        fwrite($stream, 'something');

        $this->client->download(Argument::any(), Argument::any())->willReturn($stream);

        $this->assertInternalType('array', $this->boxAdapter->read('something'));
    }

    /** @test */
    public function it_can_read_using_a_stream()
    {
        $stream = tmpfile();
        fwrite($stream, 'something');

        $this->client->download(Argument::any(), Argument::any())->willReturn($stream);

        $this->assertInternalType('array', $this->boxAdapter->readStream('something'));

        fclose($stream);
    }

    /** @test */
    public function it_can_delete_stuff()
    {
        $this->client->delete('/prefix/something')->willReturn(['.tag' => 'file']);

        $this->assertTrue($this->boxAdapter->delete('something'));
        $this->assertTrue($this->boxAdapter->deleteDir('something'));
    }

    /** @test */
    public function it_can_create_a_directory()
    {
        $this->client->createFolder('/prefix/fail/please')->willThrow(new BadRequest(new Response(409)));
        $this->client->createFolder('/prefix/pass/please')->willReturn([
            '.tag' => 'folder',
            'path_display'   => '/prefix/pass/please',
        ]);

        $this->assertFalse($this->boxAdapter->createDir('fail/please', new Config()));

        $expected = ['path' => 'pass/please', 'type' => 'dir'];
        $this->assertEquals($expected, $this->boxAdapter->createDir('pass/please', new Config()));
    }

    /** @test */
    public function it_can_list_a_single_page_of_contents()
    {
        $this->client->listFolder(Argument::type('string'), Argument::any())->willReturn(
            [
                'entries' => [
                    ['.tag' => 'folder', 'path_display' => 'dirname'],
                    ['.tag' => 'file', 'path_display' => 'dirname/file'],
                ],
                'has_more' => false,
            ]
        );

        $result = $this->boxAdapter->listContents('', true);

        $this->assertCount(2, $result);
    }

    /** @test */
    public function it_can_list_multiple_pages_of_contents()
    {
        $cursor = 'cursor';

        $this->client->listFolder(Argument::type('string'), Argument::any())->willReturn(
            [
                'entries' => [
                    ['.tag' => 'folder', 'path_display' => 'dirname'],
                    ['.tag' => 'file', 'path_display' => 'dirname/file'],
                ],
                'has_more' => true,
                'cursor' => $cursor,
            ]
        );

        $this->client->listFolderContinue(Argument::exact($cursor))->willReturn(
            [
                'entries' => [
                    ['.tag' => 'folder', 'path_display' => 'dirname2'],
                    ['.tag' => 'file', 'path_display' => 'dirname2/file2'],
                ],
                'has_more' => false,
            ]
        );

        $result = $this->boxAdapter->listContents('', true);

        $this->assertCount(4, $result);
    }

    /** @test */
    public function it_can_rename_stuff()
    {
        $this->client->move(Argument::type('string'), Argument::type('string'))->willReturn(['.tag' => 'file', 'path' => 'something']);

        $this->assertTrue($this->boxAdapter->rename('something', 'something'));
    }

    /** @test */
    public function it_will_return_false_when_a_rename_has_failed()
    {
        $this->client->move('/prefix/something', '/prefix/something')->willThrow(new BadRequest(new Response(409)));

        $this->assertFalse($this->boxAdapter->rename('something', 'something'));
    }

    /** @test */
    public function it_can_copy_a_file()
    {
        $this->client->copy(Argument::type('string'), Argument::type('string'))->willReturn(['.tag' => 'file', 'path' => 'something']);

        $this->assertTrue($this->boxAdapter->copy('something', 'something'));
    }

    /** @test */
    public function it_will_return_false_when_the_copy_process_has_failed()
    {
        $this->client->copy(Argument::any(), Argument::any())->willThrow(new BadRequest(new Response(409)));

        $this->assertFalse($this->boxAdapter->copy('something', 'something'));
    }

    /** @test */
    public function it_can_get_a_client()
    {
        $this->assertInstanceOf(Client::class, $this->boxAdapter->getClient());
    }
}
