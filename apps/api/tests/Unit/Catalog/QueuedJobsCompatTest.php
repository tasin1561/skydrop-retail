<?php

namespace Tests\Unit\Catalog;

use App\Jobs\Catalog\FetchProductJob;
use App\Jobs\Catalog\FetchSearchJob;
use Tests\TestCase;

/** Jobs already in the queue when a deploy adds a property must still unserialize and run. */
class QueuedJobsCompatTest extends TestCase
{
    public function test_jobs_without_usage_purpose_in_the_payload_still_work(): void
    {
        // Shape of a job queued before usagePurpose existed (and of one queued now without a purpose).
        $old = 'O:32:"App\Jobs\Catalog\FetchProductJob":3:{s:9:"productId";s:1:"X";s:10:"fetchJobId";s:2:"j1";s:12:"withVariants";b:1;}';
        $this->assertNull(unserialize($old)->usagePurpose);
        $this->assertNull(unserialize(serialize(new FetchSearchJob('amazon', 'earbuds', 1, 'relevance', null, 'j2')))->usagePurpose);

        $this->assertSame('paste-link', unserialize(serialize(new FetchProductJob('B0FMDLD86P', usagePurpose: 'paste-link')))->usagePurpose);
        $this->assertSame('import', unserialize(serialize(new FetchSearchJob('amazon', 'tv', usagePurpose: 'import')))->usagePurpose);
    }
}
