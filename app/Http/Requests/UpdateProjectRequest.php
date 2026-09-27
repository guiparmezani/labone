<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Support\Formato;

class UpdateProjectRequest extends StoreProjectRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && ($this->user()?->can('update', $project) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $project = $this->route('project');
        $planned = $this->user()?->isAdmin()
            ? Formato::minutosDeHoras($this->input('planned_hours'))
            : ($project instanceof Project ? $project->planned_minutes : null);

        $this->merge([
            'budget_cents' => Formato::centavos($this->input('budget')),
            'planned_minutes' => $planned,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        if (! $this->user()?->isAdmin()) {
            unset($rules['planned_hours']);
        }

        return $rules;
    }
}
