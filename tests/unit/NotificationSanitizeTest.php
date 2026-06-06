<?php namespace JumpLink\Vouchers\Tests\Unit;

use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Classes\NotificationService;

/**
 * Buyer free-text (gift message, recipient name, …) is shown in the staff
 * notification email, which is rendered as Markdown. NotificationService::mailText
 * must neutralise it so a buyer cannot inject clickable links or HTML into the
 * restaurant's inbox.
 *
 * Run from the app root:  php artisan winter:test -p JumpLink.Vouchers
 */
class NotificationSanitizeTest extends PluginTestCase
{
    public function testStripsHtml()
    {
        $this->assertStringNotContainsString('<b>', NotificationService::mailText('hi <b>bold</b>'));
        $this->assertStringNotContainsString('<script', NotificationService::mailText('<script>alert(1)</script>'));
    }

    public function testEscapesMarkdownLinkSyntax()
    {
        $out = NotificationService::mailText('Please [click here](http://phish.test)');
        // The "](" bridge that forms an inline link must be broken.
        $this->assertStringNotContainsString('](', $out);
        $this->assertStringContainsString('\\[', $out);
    }

    public function testBreaksBareUrlAutolinks()
    {
        // The scheme separator is escaped (http\://) so the markdown autolinker
        // won't match — i.e. no bare scheme:// survives to become a link.
        $this->assertStringNotContainsString('http://', NotificationService::mailText('visit http://evil.test'));
        $this->assertStringNotContainsString('https://', NotificationService::mailText('https://evil.test'));
    }

    public function testLeavesPlainTextReadable()
    {
        $this->assertSame('Frohe Weihnachten!', strip_tags(html_entity_decode(
            str_replace('\\', '', NotificationService::mailText('Frohe Weihnachten!')),
        )));
    }
}
