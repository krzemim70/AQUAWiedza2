@if ($errors->any())
    <div role="alert" class="alert alert-error" tabindex="-1">
        <p><strong>Popraw dane w formularzu:</strong></p>
        <ul>
            @foreach ($errors->keys() as $key)
                <li><a href="#c-{{ str_replace('.', '-', $key) }}">{{ $errors->first($key) }}</a></li>
            @endforeach
        </ul>
    </div>
@endif
