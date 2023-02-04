#!/bin/bash
SCRIPT_DIR=$(dirname -- "$( readlink -f -- "$0"; )");
php $SCRIPT_DIR/pshare.php $@ 
