# ~/.bash_profile: executed by bash(1) for non-login shells.

# "-s" Start ssh agent for Bourne-type shells (sh or ksh).
# Use "-c" for C shell.
eval `ssh-agent -s` > /dev/null 2>&1
ssh-add /var/run/secrets/mass_id_rsa
