# AI SEO Analyzer

The AI SEO Analyzer is a Drupal module that integrates with the models AI module
provides to provide SEO analysis directly within the node view. It allows
users to generate and customize SEO reports using AI-driven insights, storing
all results in the database for easy access and reference. This module is a
practical tool for site administrators and content managers looking to
enhance their content's SEO through advanced, AI-powered analytics.

## Additional requirements

This module is a part of [AI](https://www.drupal.org/project/ai)
ecosystem and it is a required module for this to work.

## Post-installation

- Configure the AI module at /admin/config/ai/providers
- Configure your selected provider at /admin/config/ai/providers
- Select your provider and model at /admin/config/ai/seo
- Set correct permissions at /admin/people/permissions/module/ai_seo
- After that you can generate reports from the node views using SEO Analyzer tab
