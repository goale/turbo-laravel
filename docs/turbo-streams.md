# Turbo Streams

As mentioned earlier, out of everything Turbo provides, it's Turbo Streams that benefits the most from a back-end integration.

Turbo Drive will get your pages behaving like an SPA and Turbo Frames will allow you to have a finer grained control of chunks of your page instead of replacing the entire page when a form is submitted or a link is clicked.

However, sometimes you want to update _multiple_ parts of your page at the same time. For instance, after a form submission to create a comment, you may want to append the comment to the comment's list and also update the comment's count in the page. You may achieve that with Turbo Streams.

Form submissions will get annotated by Turbo with a `Accept: text/vnd.turbo-stream.html` header (besides the other normal Content Types). This will indicate to your back-end that you can return a Turbo Stream response for that form submission if you want to.

Here's an example of a route handler detecting and returning a Turbo Stream response to a form submission:

```php
Route::post('posts/{post}/comments', function (Post $post) {
    $comment = $post->comments()->create(/** params */);

    if (request()->wantsTurboStream()) {
        return response()->turboStream($comment);
    }

    return back();
});
```

The `request()->wantsTurboStream()` macro added to the request will check if the request accepts Turbo Stream and return `true` or `false` accordingly.

The `response()->turboStream()` macro may be used to generate streams, but you may also use the `turbo_stream()` helper function. From now on, the docs will be using the helper function, but you may use either one of those. Using the function, this example would be:

```php
Route::post('posts/{post}/comments', function (Post $post) {
    $comment = $post->comments()->create(/** params */);

    if (request()->wantsTurboStream()) {
        return turbo_stream($comment);
    }

    return back();
});
```

Here's what the HTML response will look like:

```html
<turbo-stream action="append" target="comments">
    <template>
        <div id="comment_123">
            <p>Hello, World</p>
        </div>
    </template>
</turbo-stream>
```

Most of these things were "guessed" based on the [naming conventions](#conventions) we talked about earlier. But you can override most things, like so:

```php
return turbo_stream($comment)->target('post_comments');
```

Although it's handy to pass the model instance to the `turbo_stream()` function - which will be used to decide the default values of the Turbo Stream response based on the model's current state, sometimes you may want to build a Turbo Stream response manually, which can be achieved like so:

```php
return turbo_stream()
    ->target('comments')
    ->action('append')
    ->view('comments._comment', ['comment' => $comment]);
```

There are 7 _actions_ in Turbo Streams. They are:

* `append` & `prepend`: to add the elements in the target element at the top or at the bottom of its contents, respectively
* `before` & `after`: to add the elements next to the target element before or after, respectively
* `replace`: will replace the existing element entirely with the contents of the `template` tag in the Turbo Stream
* `update`: will keep the target and only replace the contents of it with the contents of the `template` tag in the Turbo Stream
* `remove`: will remove the element. This one doesn't need a `<template>` tag. It accepts either an instance of a Model or the DOM ID of the element to be removed as a string.

Which means you will find shorthand methods for them all, like:

```php
turbo_stream()->append($comment);
turbo_stream()->prepend($comment);
turbo_stream()->before($comment);
turbo_stream()->after($comment);
turbo_stream()->replace($comment);
turbo_stream()->update($comment);
turbo_stream()->remove($comment);
```

For these shorthand stream builders, you may pass an instance of an Eloquent model, which will be used to figure out things like `target`, `action`, and the `view` partial as well as the view data passed to them.

Alternativelly, you may also pass strings to the shorthand stream builders, which will be used as the target, and an optional content string, which will be rendered instead of a partial, for instance:

```php
turbo_stream()->append('statuses', __('Comment was successfully created!'));
```

The optional content parameter expects either a string, a view instance, or an instance of Laravel's `Illuminate\Support\HtmlString`, so you could do something like:

```php
turbo_stream()->append('some_dom_id', view('greetings', ['name' => 'Tester']));
```

Or more explicitly by passing an instance of the `HtmlString` as content:

```php
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

turbo_stream()->append('statuses', new HtmlString(
    Blade::render('<div>Hello, {{ $name }}</div>', ['name' => 'Tony'])
));
```

Which will result in a Turbo Stream like this:

```html
<turbo-stream target="statuses" action="append">
    <template>
        <div>Hello, Tony</div>
    </template>
</turbo-stream>
```

For both the `before` and `after` methods you need additional calls to specify the view template you want to insert, since the given model/string will only be used to specify the target, something like:

```php
turbo_stream()
    ->before($comment)
    ->view('comments._flash_message', [
        'message' => __('Comment was created!'),
    ]);
```

Just like the other shorthand stream builders, you may also pass an option content string or `HtmlString` instance to the `before` and `after` shorthands. When doing that, you don't need to specify the view section.

```php
turbo_stream()->before($comment, __('Oh, hey!'));
```

You can read more about Turbo Streams in the [Turbo Handbook](https://turbo.hotwired.dev/handbook/streams).

The shorthand methods return a pending object for the response which you can chain and override everything you want before it's rendered:

```php
return turbo_stream()
    ->append($comment)
    ->view('comments._comment_card', ['comment' => $comment]);
```

As mentioned earlier, passing a model to the `turbo_stream()` helper will pre-fill the pending response object with some defaults based on the model's state.

It will build a `remove` Turbo Stream if the model was deleted (or if it is trashed - in case it's a Soft Deleted model), an `append` if the model was recently created (which you can override the action as the second parameter), a `replace` if the model was just updated (you can also override the action as the second parameter.) Here's how overriding would look like:

```php
return turbo_stream($comment, 'append');
```

## Turbo Streams Combo

You may combine multiple Turbo Stream responses in a single one like so:

```php
return turbo_stream([
    turbo_stream()
        ->append($comment)
        ->target(dom_id($comment->post, 'comments')),
    turbo_stream()
        ->update(dom_id($comment->post, 'comments_count'), view('posts._comments_count', ['post' => $comment->post])),
]);
```

Although this is a valid option, it might feel like too much work for a controller. If that's the case, use [Custom Turbo Stream Views](#custom-turbo-stream-views).

## Custom Turbo Stream Views

If you're not using the model partial [convention](#conventions) or if you have some more complex Turbo Stream constructs to build, you may use the `response()->turboStreamView()` version instead and specify your own Blade view where Turbo Streams will be created. This is what that looks like:

```php
return response()->turboStreamView('comments.turbo.created_stream', [
    'comment' => $comment,
]);
```

Similarly, you may prefer to use the helper function `turbo_stream_view()`:

```php
return turbo_stream_view('comments.turbo.created_stream', [
    'comment' => $comment,
]);
```

And here's an example of a more complex custom Turbo Stream view:

```blade
@include('layouts.turbo.flash_stream')

<turbo-stream target="@domid($comment->post, 'comments')" action="append">
    <template>
        @include('comments._comment', ['comment' => $comment])
    </template>
</turbo-stream>
```

Remember, these are Blade views, so you have the full power of Blade at your hands. In this example, we're including a shared Turbo Stream partial which could append any flash messages we may have. That `layouts.turbo.flash_stream` could look like this:

```blade
@if (session()->has('status'))
<turbo-stream target="notice" action="append">
    <template>
        @include('layouts._flash')
    </template>
</turbo-stream>
@endif
```

Similar to the `<x-turbo-frame>` Blade component, there's also a `<x-turbo-stream>` Blade component that can simplify things quite a bit. It has the same convention of figureing out the DOM ID of the target when you're passing a model instance or an array as the `<x-turbo-frame>` component applied to the `target` attribute here. When using the component version, there's also no need to specify the template wrapper for the Turbo Stream tag, as that will be added by the component itself. So, the same example would look something like this:

```blade
@include('layouts.turbo.flash_stream')

<x-turbo-stream :target="[$comment->post, 'comments']" action="append">
    @include('comments._comment', ['comment' => $comment])
</x-turbo-stream>
```

I hope you can see how powerful this can be to reusing views.

## Broadcasting Turbo Streams Over WebSockets With Laravel Echo

So far, we have used Turbo Streams over HTTP to handle the case of updating multiple parts of the page for a single user after a form submission. In addition to that, you may want to broadcast model changes over WebSockets to all users that are viewing the same page. Although nice, **you don't have to use WebSockets if you don't have the need for it. You may still benefit from Turbo Streams over HTTP.**

We can broadcast to all users over WebSockets those exact same Turbo Stream tags we are returning to a user after a form submission. That makes use of Laravel Echo and Laravel's Broadcasting component.

You may still feed the user making the changes with Turbo Streams over HTTP and broadcast the changes to other users over WebSockets. This way, the user making the change will have an instant feedback compared to having to wait for a background worker to pick up the job and send it to them over WebSockets.

First, you need to uncomment the Laravel Echo setup on your [`resources/js/bootstrap.js`](https://github.com/laravel/laravel/blob/9.x/resources/js/bootstrap.js#L21-L34) file and make sure you compile your assets after doing that by running:

```bash
npm run dev
```

Then, you'll need to setup the [Laravel Broadcasting](https://laravel.com/docs/9.x/broadcasting) component for your app. One of the first steps is to configure your environment variables to look something like this:

```dotenv
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=us2
PUSHER_APP_HOST=websockets.test
PUSHER_APP_PORT=6001

MIX_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
MIX_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
MIX_PUSHER_APP_HOST="localhost"
MIX_PUSHER_APP_PORT="${PUSHER_APP_PORT}"
MIX_PUSHER_APP_USE_SSL=false
```

Notice that some of these environment variables are used by your front-end assets during compilation. That's why you see some duplicates that are just prefixed with `MIX_`.

These settings assume you're using the [Laravel WebSockets](https://github.com/beyondcode/laravel-websockets) package. Check out the Echo configuration at [resources/js/bootstrap.js](https://github.com/laravel/laravel/blob/9.x/resources/js/bootstrap.js#L21-L34) to see which environment variables are needed during build time. You may also use [Pusher](https://pusher.com/) or [Ably](https://ably.io/) instead of the Laravel WebSockets package, if you don't want to host it yourself.

<a name="broadasting-model-changes"></a>
## Broadcasting Model Changes

With Laravel Echo properly configured, you may now broadcast model changes using WebSockets. First thing you need to do is use the `Broadcasts` trait in your model:

```php
use Tonysm\TurboLaravel\Models\Broadcasts;

class Comment extends Model
{
    use Broadcasts;
}
```

This trait will add some methods to your model that you can use to trigger broadcasts. Here's how you can broadcast appending a new comment to all users visiting the post page:

```php
Route::post('posts/{post}/comments', function (Post $post) {
    $comment = $post->comments()->create(/** params */);

    $comment->broadcastAppend()->later();

    if (request()->wantsTurboStream()) {
        return turbo_stream($comment);
    }

    return back();
});
```

Here are the methods now available to your model:

```php
$comment->broadcastAppend();
$comment->broadcastPrepend();
$comment->broadcastBefore('target_dom_id');
$comment->broadcastAfter('target_dom_id');
$comment->broadcastReplace();
$comment->broadcastUpdate();
$comment->broadcastRemove();
```

These methods will assume you want to broadcast the Turbo Streams to your model's channel. However, you will also find alternative methods where you can specify either a model or the broadcasting channels you want to send the broadcasts to:

```php
$comment->broadcastAppendTo($post);
$comment->broadcastPrependTo($post);
$comment->broadcastBeforeTo($post, 'target_dom_id');
$comment->broadcastAfterTo($post, 'target_dom_id');
$comment->broadcastReplaceTo($post);
$comment->broadcastUpdateTo($post);
$comment->broadcastRemoveTo($post);
```

These `broadcastXTo()` methods accept either a model, a channel instance or an array containing both of these. When it receives a model, it will guess the channel name using the broadcasting channel convention (see [#conventions](#conventions)).

All of these broadcasting methods return an instance of a `PendingBroadcast` class that will only dispatch the broadcasting job when that pending object is being garbage collected. Which means that you can control a lot of the properties of the broadcast by chaining on that instance before it goes out of scope, like so:

```php
$comment->broadcastAppend()
    ->to($post)
    ->view('comments/_custom_view_partial', [
        'comment' => $comment,
        'post' => $post,
    ])
    ->toOthers() // Do not send to the current user.
    ->later(); // Dispatch a background job to send.
```

You may want to hook those methods in the model events of your model to trigger Turbo Stream broadcasts whenever your models are changed in any context, such as:

```php
class Comment extends Model
{
    use Broadcasts;

    protected static function booted()
    {
        static::created(function (Comment $comment) {
            $comment->broadcastPrependTo($comment->post)
                ->toOthers()
                ->later();
        });

        static::updated(function (Comment $comment) {
            $comment->broadcastReplaceTo($comment->post)
                ->toOthers()
                ->later();
        });

        static::deleted(function (Comment $comment) {
            $comment->broadcastRemoveTo($comment->post)
                ->toOthers()
                ->later();
        });
    }
}
```

In case you want to broadcast all these changes automatically, instead of specifying them all, you may want to add a `$broadcasts` property to your model, which will instruct the `Broadcasts` trait to trigger the Turbo Stream broadcasts for the created, updated and deleted model events, like so:

```php
class Comment extends Model
{
    use Broadcasts;

    protected $broadcasts = true;
}
```

This will achieve almost the same thing as the example where we registered the model events manually, with a couple nuanced differences. First, by default, it will broadcast an `append` Turbo Stream to newly created models. You may want to use `prepend` instead. You can do so by using an array with a `insertsBy` key and `prepend` action as value instead of a boolean, like so:

```php
class Comment extends Model
{
    use Broadcasts;

    protected $broadcasts = [
        'insertsBy' => 'prepend',
    ];
}
```

This will also automatically hook into the model events, but instead of broadcasting new instances as `append` it will use `prepend`.

Secondly, it will send all changes to this model's broadacsting channel, except for when the model is created (since no one would be listening to its direct channel). In our case, we want to direct the broadcasts to the post linked to this model instead. We can achieve that by adding a `$broadcastsTo` property to the model, like so:

```php
class Comment extends Model
{
    use Broadcasts;

    protected $broadcasts = [
        'insertsBy' => 'prepend',
    ];

    protected $broadcastsTo = 'post';

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
```

That property can either be a string that contains the name of a relationship of this model or an array of relationships.

Alternatively, you may prefer to have more control over where these broadcasts are being sent to by implementing a `broadcastsTo` method in your model instead of using the property. This way, you can return a single model, a broadcasting channel instance or an array containing either of them, like so:

```php
use Illuminate\Broadcasting\Channel;

class Comment extends Model
{
    use Broadcasts;

    protected $broadcasts = [
        'insertsBy' => 'prepend',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function broadcastsTo()
    {
        return [
            $this,
            $this->post,
            new Channel('full-control'),
        ];
    }
}
```

Newly created models using the auto-broadcasting feature will be broadcasted to a pluralized version of the model's basename. So if you have a `App\Models\PostComment`, you may expect broadcasts of newly created models to be sent to a private channel called `post_comments`. Again, this convention is only valid for newly created models. Updates/Removals will still be sent to the model's own private channel by default using Laravel's convention for channel names. You may want to specify the channel name for newly created models to be broadcasted to with the `stream` key:

```php
class Comment extends Model
{
    use Broadcasts;

    protected $broadcasts = [
        'stream' => 'some_comments',
    ];
}
```

Having a `$broadcastsTo` property or implementing the `broadcastsTo()` method in your model will have precedence over this, so newly created models will be sent to the channels specified on those places instead of using the convention or the `stream` option.

<a name="listening-to-broadcasts"></a>
## Listening to Turbo Stream Broadcasts

You may listen to a Turbo Stream broadcast message on your pages by adding the custom HTML tag `<turbo-echo-stream-source>` that is published to your application's assets (see [here](https://github.com/tonysm/turbo-laravel/blob/main/stubs/resources/js/elements/turbo-echo-stream-tag.js)). You need to pass the channel you want to listen to broadcasts on using the `channel` attribute of this element, like so.

```blade
<turbo-echo-stream-source
    channel="App.Models.Post.{{ $post->id }}"
/>
```

You may prefer using the convenient `<x-turbo-stream-from>` Blade component, passing the model as the `source` prop to it, something like this:

```blade
<x-turbo-stream-from :source="$post" />
```

By default, it expects a private channel, so the it must be used in a page for already authenticated users. You may control the channel type in the tag with a `type` attribute.

```blade
<x-turbo-stream-from :source="$post" type="public" />
```

To register the Broadcast Auth Route you may use Laravel's built-in conventions as well:

**file: routes/channel.php**
```php
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(Post::class, function (User $user, Post $post) {
    return $user->belongsToTeam($post->team);
});
```

You may want to read the [Laravel Broadcasting](https://laravel.com/docs/9.x/broadcasting) documentation.

<a name="broadcasting-to-others"></a>
## Broadcasting Turbo Streams to Other Users Only

As mentioned erlier, you may want to feed the current user with Turbo Streams using HTTP requests and only send the broadcasts to other users. There are a couple ways you can achieve that.

First, you can chain on the broadcasting methods, like so:

```php
$comment->broadcastAppendTo($post)
    ->toOthers();
```

Second, you can use the Turbo Facade like so:

```php
use Tonysm\TurboLaravel\Facades\Turbo;

Turbo::broadcastToOthers(function () {
    // ...
});
```

This way, any broadcast that happens inside the scope of the Closure will only be sent to other users.

Third, you may use that same method but without the Closure inside a ServiceProvider, for instance, to instruct the package to only send turbo stream broadcasts to other users globally:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Tonysm\TurboLaravel\Facades\Turbo;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Turbo::broadcastToOthers();
    }
}
```

[Continue to Validation Response Redirects...](/docs/{{version}}/validation-response-redirects)