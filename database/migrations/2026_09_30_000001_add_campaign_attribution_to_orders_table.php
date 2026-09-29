<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where did the order come from? Filled from the ad / UTM parameters the customer landed with
 * (see App\Support\Attribution and App\Http\Middleware\CaptureAttribution).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // standard UTM tags
            $table->string('utm_source')->nullable()->after('source');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('utm_content')->nullable()->after('utm_campaign');
            $table->string('utm_term')->nullable()->after('utm_content');

            // Meta ad IDs / names (from the ad's URL parameters)
            $table->string('campaign_id', 64)->nullable()->after('utm_term');
            $table->string('campaign_name')->nullable()->after('campaign_id');
            $table->string('adset_id', 64)->nullable()->after('campaign_name');
            $table->string('adset_name')->nullable()->after('adset_id');
            $table->string('ad_id', 64)->nullable()->after('adset_name');
            $table->string('ad_name')->nullable()->after('ad_id');
            $table->string('placement', 100)->nullable()->after('ad_name');
            $table->string('site_source', 50)->nullable()->after('placement'); // fb, ig, msg, an

            // click ids / browser ids (also useful for Meta Conversions API matching)
            $table->string('fbclid', 500)->nullable()->after('site_source');
            $table->string('gclid', 255)->nullable()->after('fbclid');
            $table->string('fbc', 500)->nullable()->after('gclid');
            $table->string('fbp', 255)->nullable()->after('fbc');

            $table->text('landing_page')->nullable()->after('fbp');
            $table->text('referrer')->nullable()->after('landing_page');
            // raw first touch + last touch data
            $table->json('attribution')->nullable()->after('referrer');

            $table->index('utm_campaign');
            $table->index('campaign_id');
            $table->index('ad_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['utm_campaign']);
            $table->dropIndex(['campaign_id']);
            $table->dropIndex(['ad_id']);
            $table->dropColumn([
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
                'campaign_id', 'campaign_name', 'adset_id', 'adset_name', 'ad_id', 'ad_name', 'placement', 'site_source',
                'fbclid', 'gclid', 'fbc', 'fbp', 'landing_page', 'referrer', 'attribution',
            ]);
        });
    }
};
