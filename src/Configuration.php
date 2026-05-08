<?php

namespace AuthEcust;

use Illuminate\Http\Request;

class Configuration
{
    public function render(Request $request)
    {
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'official_enabled' => 'nullable|boolean',
                'smtp_enabled' => 'nullable|boolean',
                'eduroam_enabled' => 'nullable|boolean',
            ]);

            option([
                'auth_ecust_official_enabled' => $request->has('official_enabled'),
                'auth_ecust_smtp_enabled' => $request->has('smtp_enabled'),
                'auth_ecust_eduroam_enabled' => $request->has('eduroam_enabled'),
            ]);

            return json(trans('AuthEcust::config.saved'), 0);
        }

        return view('AuthEcust::config', [
            'official_enabled' => (bool) option('auth_ecust_official_enabled', true),
            'smtp_enabled' => (bool) option('auth_ecust_smtp_enabled', true),
            'eduroam_enabled' => (bool) option('auth_ecust_eduroam_enabled', true),
        ]);
    }
}
