## App Rules

### /resources folder structure

/css - follows laravel convention
/js - follows laravel convention

/views/components - blade components, follows laravel convention
/views/flux - only fluxui components and stuff
/livewire/pages/ - only full page components (no Volt components, only blade views)
/livewire/components/ - only normal livewire components
/livewire/layouts/ - only layouts for livewire full page components

- Only /livewire/pages/ can have subfolders according to the routes/web.php file!

### /app folder structure
/app/Actions - Actions with no subfolders
/app/Livewire/Pages/ - mirrors the blade part in /resources folder
/app/Livewire/Layouts/ - mirrors the blade part in /resources folder
/app/Livewire/Components/ - mirrors the blade part in /resources folder

### Livewire principles:
- No Volt components, only the classic Livewire approach
- All DB writes should be done through Actions (app/Actions/)

### All Actions should be invokable classes with this structure
```
    public function __invoke(array $data)
    {
        $this->data = $data;
        
        $this->authorize();
        $this->validate();
        return $this->execute();
    }
```

Validate and authorize methods should use classic Laravel approach,
it will be caught by Livewire to get the end user the proper feedback.

### Authorization rules
- Use Policies or Gates

## Testing
- Use Pest PHP for all tests
- General ModelFactory for each model

### Testing Actions
- Generate a test for each Action
- Test validation (both pass and fail)
- Test authorization (both pass and fail)
- Test database changes

### Testing Livewire components
- Smoke test (component can render)
- Test each Livewire action