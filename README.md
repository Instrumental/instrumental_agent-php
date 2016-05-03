WARNING: WORK IN PROGRESS. NOT PRODUCTION READY.

# Instrumental PHP Agent

Instrumental is a [application monitoring platform](https://instrumentalapp.com) built for developers who want a better understanding of their production software. Powerful tools, like the [Instrumental Query Language](https://instrumentalapp.com/docs/query-language), combined with an exploration-focused interface allow you to get real answers to complex questions, in real-time.

This agent supports custom metric monitoring for PHP applications. It provides high-data reliability at high scale, without ever blocking your process or causing an exception.

## Composer Installation

```bash
composer require instrumental/instrumental_agent
```

Visit [instrumentalapp.com](https://instrumentalapp.com) and create an account, then initialize the agent with your API key, found in the Docs section.

## Usage

```php
$I = new \Instrumental\Agent();
$I->setApiKey("YOUR_API_KEY");
$I->setEnabled(is_production);
```

You'll probably want something like the above, only enabling the agent in production mode so you don't have development and production data writing to the same value. Or you can setup two projects, so that you can verify stats in one, and release them to production in another.

Now you can begin to use Instrumental to track your application.

```php
$I->gauge('load', 1.23);                                # value at a point in time

$I->increment('signups');                               # increasing value, think "events"

$post = $I->time('query_time', function(){              # time a block of code
  return Post->find(1);
});
$post = $I->time_ms('query_time_in_ms', function(){     # prefer milliseconds?
  return Post->find(1);
});
```

**Note**: For your app's safety, the agent is meant to isolate your app from any problems our service might suffer. If it is unable to connect to the service, it will discard data after reaching a low memory threshold.

Want to track an event (like an application deploy, or downtime)? You can capture events that are instantaneous, or events that happen over a period of time.

```php
$I->notice('Jeffy deployed rev ef3d6a'); # instantaneous event
$I->notice('Testing socket buffer increase', time() - (3*24*60*60), 20*60); # an event (three days ago) with a duration (20 minutes)
```

## Agent Control

Need to quickly disable the agent? Use `$I->setEnabled(FALSE);` on initialization and you don't need to change any application code.

## Troubleshooting & Help

We are here to help. Email us at [support@instrumentalapp.com](mailto:support@instrumentalapp.com).


## Release Process

1. Pull latest master
2. Merge feature branch(es) into master
3. `script/test`
4. Increment version in code
5. Update [CHANGELOG.md](CHANGELOG.md)
6. Commit "Release version vX.Y.Z"
7. Push to GitHub
8. Tag version: `git tag 'vX.Y.Z' && git push --tags` (GitHub webhook will tell packagist and release a new version)
9. Verify update on https://packagist.org/packages/instrumental/instrumental_agent
10. Refresh documentation on instrumentalapp.com


## Version Policy

This library follows [Semantic Versioning 2.0.0](http://semver.org).
